<?php

namespace Modules\Blog\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Blog\Models\Blog;
use Illuminate\Support\Facades\Storage;

class Main extends Controller
{
    /**
     * Provide class.
     */
    private $provider = Blog::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'blogging.';

    /**
     * View path.
     */
    private $view_path = 'blog::';

    /**
     * Parameter name.
     */
    private $parameter_name = 'blog';

    /**
     * Title of this crud.
     */
    private $title = 'Blog post';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'Blog posts';

    private function getFields($class='col-md-4')
    {
        $fields = [];
        
        // Main content section (like WordPress post editor)
        $fields[] = ['class'=>'col-md-8', 'ftype'=>'input', 'name'=>'Title', 'id'=>'title', 'placeholder'=>'Add title', 'required'=>true];
        $fields[] = ['class'=>'col-md-8', 'ftype'=>'input', 'name'=>'Slug', 'id'=>'slug', 'placeholder'=>'Enter slug (URL-friendly version of title)', 'required'=>true];
        $fields[] = ['class'=>'col-md-12', 'ftype'=>'textarea', 'name'=>'Content', 'id'=>'content', 'placeholder'=>'Start writing or type / to choose a block', 'required'=>true];
        
        // Featured Image (WordPress-style)
        $fields[] = ['class'=>'col-md-4', 'ftype'=>'image', 'name'=>'Featured Image', 'id'=>'featured_image', 'placeholder'=>'Set featured image', 'required'=>false, 'help'=>'Recommended size: 1200x628 pixels'];
        
        // Post Settings (like WordPress sidebar)
        $fields[] = ['class'=>'col-md-12', 'ftype'=>'select', 'name'=>'Status', 'id'=>'status', 'placeholder'=>'Select status', 'data'=>[
            'draft'=>'Draft', 
            'published'=>'Published'
        ], 'required'=>true];
        
        $fields[] = ['class'=>'col-md-12', 'ftype'=>'bool', 'name'=>'Is Featured', 'id'=>'is_featured', 'placeholder'=>'Stick to the top of the blog?', 'required'=>false];
        
        // SEO section (like Yoast SEO)
        $fields[] = ['class'=>'col-md-12', 'ftype'=>'section', 'name'=>'SEO Settings', 'id'=>'seo_settings'];
        $fields[] = ['class'=>'col-md-8', 'ftype'=>'input', 'name'=>'Meta Title', 'id'=>'meta_title', 'placeholder'=>'Enter SEO title', 'required'=>false, 'help'=>'Maximum 60 characters recommended'];
        $fields[] = ['class'=>'col-md-8', 'ftype'=>'textarea', 'name'=>'Meta Description', 'id'=>'meta_description', 'placeholder'=>'Enter meta description', 'required'=>false, 'help'=>'Maximum 160 characters recommended'];
        $fields[] = ['class'=>'col-md-8', 'ftype'=>'input', 'name'=>'Meta Keywords', 'id'=>'meta_keywords', 'placeholder'=>'Enter keywords, separated by commas', 'required'=>false];
        
        // Excerpt (like WordPress)
        $fields[] = ['class'=>'col-md-12', 'ftype'=>'textarea', 'name'=>'Excerpt', 'id'=>'excerpt', 'placeholder'=>'Write an excerpt (optional)', 'required'=>false, 'help'=>'Excerpts are optional hand-crafted summaries of your content'];
        
        // Categories and Tags (if you want to add these later)
        // $fields[] = ['class'=>'col-md-4', 'ftype'=>'select2', 'name'=>'Categories', 'id'=>'categories', 'placeholder'=>'Select categories', 'multiple'=>true, 'required'=>false];
        // $fields[] = ['class'=>'col-md-4', 'ftype'=>'select2', 'name'=>'Tags', 'id'=>'tags', 'placeholder'=>'Add tags', 'multiple'=>true, 'required'=>false];

        return $fields;
    }

    private function getFilterFields(){
        $fields=$this->getFields('col-md-3');
        return $fields;
    }

    /**
     * Auth checker function for the crud.
     */
    private function authChecker()
    {
        $this->adminOnly();
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $this->authChecker();

        $items = $this->provider::orderBy('id', 'desc');
        $items = $items->paginate(config('settings.paginate'));

        $setup=[
            'usefilter'=>null,
            'title'=>__('Blog'),
            'action_link2'=>route($this->webroute_path.'create'),
            'action_name2'=>__('Create Blog posts'),
            'items'=>$items,
            'item_names'=>$this->titlePlural,
            'webroute_path'=>$this->webroute_path,
            'fields'=>$this->getFields('col-md-3'),
            'filterFields'=>$this->getFilterFields(),
            'custom_table'=>true,
            'parameter_name'=>$this->parameter_name,
            'parameters'=>count($_GET) != 0,
            'hidePaging'=>false,
        ];
        return view($this->view_path.'index', ['setup' => $setup]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        $this->authChecker();
        
        $fields = $this->getFields('col-md-6');
       

        return view($this->view_path.'edit', ['setup' => [
            'title'=>__('crud.new_item', ['item'=>__('Blog')]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'action'=>route($this->webroute_path.'store')
        ],
        'fields'=>$fields ]);
    }

    private function handleBlogData(Request $request, $item = null)
    {

      
        $data = [
            'title' => $request->title,
            'slug' => $request->slug,
            'content' => $request->content_hidden,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'meta_keywords' => $request->meta_keywords,
            'status' => $request->status,
            'is_featured' => $request->has('is_featured'),
            'excerpt' => $request->excerpt,
        ];

        // Handle featured image upload if present
        if ($request->hasFile('featured_image')) {
            // Delete old image if exists
            if ($item && $item->featured_image) {
                Storage::disk('public')->delete($item->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        }

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $this->authChecker();
        
        $data = $this->handleBlogData($request);
        
        $item = $this->provider::create($data);

        return redirect()->route($this->webroute_path.'index')
            ->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $item->title;
       

        // Set values for all fields from the item
        foreach ($fields as $key => $field) {
            switch ($field['id']) {
                case 'content':
                    $fields[$key]['value'] = $item->content;
                    break;
                case 'meta_title':
                    $fields[$key]['value'] = $item->meta_title;
                    break;
                case 'meta_description':
                    $fields[$key]['value'] = $item->meta_description;
                    break;
                case 'meta_keywords':
                    $fields[$key]['value'] = $item->meta_keywords;
                    break;
                case 'slug':
                    $fields[$key]['value'] = $item->slug;
                    break;
                case 'status':
                    $fields[$key]['value'] = $item->status;
                    break;
                case 'is_featured':
                    $fields[$key]['value'] = $item->is_featured;
                    break;
                case 'excerpt':
                    $fields[$key]['value'] = $item->excerpt;
                    break;
                case 'featured_image':
                    $fields[$key]['value'] = $item->featured_image;
                    break;
            }
        }
        

        $parameter = [];
        $parameter[$this->parameter_name] = $item->id;

        return view($this->view_path.'edit', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$item->title]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'isupdate'=>true,
            'action'=>route($this->webroute_path.'update', ['item'=>$item->id]),
        ],
        'fields'=>$fields ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        
        $data = $this->handleBlogData($request, $item);
        $item->update($data);

        return redirect()->route($this->webroute_path.'index')
            ->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {

        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->delete();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item'=>__($this->title)]));
    }

    public function clone($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->clone();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_cloned', ['item'=>__($this->title)]));
    }

    //ADDITIONAL NEEDED FUNCTIONS

    /**
     * Get all blog posts with pagination
     * @param Request $request
     * @return Response
     */
    public function all(Request $request)
    {
        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);

        $items = $this->provider::where('status', 'published')
            ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'created_at', 'updated_at', 'read_time'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        $data = $items->items();
       
        return response()->json([
            'status' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $items->lastPage(), 
                'per_page' => $limit,
                'total' => $items->total()
            ]
        ]);
    }

    /**
     * Get single blog post by slug
     * @param string $slug
     * @return Response
     */
    public function single($slug)
    {
        $item = $this->provider::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $item
        ]);
    }

   
}
