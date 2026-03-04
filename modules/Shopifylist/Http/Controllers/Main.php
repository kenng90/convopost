<?php

namespace Modules\Shopifylist\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Contact;

class Main extends Controller
{
    public function getJSONFile(){
        $company = $this->getCompany();
        $data = $this->getData($company,true);
        $json = json_encode($data);
        $filename = 'shopify_data.json';
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
        return response($json, 200, $headers);
    }

    public function getData($force_reload = false)
    {
        $company = $this->getCompany();
        $filename = storage_path('shopifylist_'.$company->id.'.json');
        if(file_exists($filename) && !$force_reload){
            $data = json_decode(file_get_contents($filename));

            //Check if the data is empty
            if(empty($data)){
                $data = $this->getDataFromShopify($company);
                file_put_contents($filename, json_encode($data));
            }
            return $data;
        }else{
            $data = $this->getDataFromShopify($company);
            file_put_contents($filename, json_encode($data));
            return $data;
        }
    }




    public function getDataFromShopify(Company $company,$force_reload = false)
    {
        $predefined_json_data = $company->getConfig('predefined_json_data');
        if(!empty($predefined_json_data) && !$force_reload){
            $data = json_decode(file_get_contents($predefined_json_data));
            return $data;
        }

        
        $storeName = $company->getConfig('shopify_store_name');
        $apiVersion = $company->getConfig('shopify_api_version');
        $accessToken = $company->getConfig('shopify_access_token');

        if(config('settings.is_demo',false)){
            $storeName = 'vbz32s-vz'; // Replace with your store name
            $apiVersion = '2024-10'; // Replace with the desired API version
            $accessToken = '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'; // Replace with your access token
        }
    
        if(empty($storeName) || empty($apiVersion) || empty($accessToken)){
            return [];
        }

        Log::info('Shopifylist: getDataFromShopify', ['storeName' => $storeName, 'apiVersion' => $apiVersion, 'accessToken' => $accessToken]);

        $shopify_currency = $company->getConfig('shopify_currency', '$');

        // Format the API endpoints
        $productsUrl = "https://{$storeName}.myshopify.com/admin/api/{$apiVersion}/products.json";

        // Get products
        $productsResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get($productsUrl);

        $products = $productsResponse->json('products');

       
        // Process products
        $productsData = [];
        foreach($products as $product){
            $productsData[] = [
                'title' => $product['title'],
                'description' => $shopify_currency . ' ' . $product['variants'][0]['price'],
                'image' => isset($product['images'][0]) ? str_replace('.jpg?', '_100x100.jpg?', $product['images'][0]['src']) : null,
                'link' => "https://{$storeName}.myshopify.com/products/".$product['handle'],
            ];
        }
       
        // Return only products data
        $data = $productsData;

        Log::info('Shopifylist: getDataFromShopify', ['data' => $data]);

        return $data;
    }
    


    //Find the User's Orders
    public function getOrders(Request $request){

        try {
            $company = $this->getCompany();
            
            //Get the contact
            $contact = Contact::find($request->contact_id);

            $storeName = $company->getConfig('shopify_store_name');
            $apiVersion = $company->getConfig('shopify_api_version');
            $accessToken = $company->getConfig('shopify_access_token');

            if(config('settings.is_demo',false)){
                $storeName = 'vbz32s-vz'; // Replace with your store name
                $apiVersion = '2024-10'; // Replace with the desired API version
                $accessToken = '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'; // Replace with your access token
            }
           

            // Format the API endpoint
            $url = "https://{$storeName}.myshopify.com/admin/api/{$apiVersion}/orders.json";

            // Make the HTTP request
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->get($url, [
                'email' => $contact->email,
            ]);

            return response()->json([
                'url' => $url,
                'success' => $response->successful(),
                'data' => $response->successful() ? $response->json('orders') : null,
                'error' => !$response->successful(),
                'message' => $response->successful() ? null : ($response->json('errors') ?? 'Failed to retrieve orders.'),
                'status' => $response->status()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => true,
                'data' => null,
                'message' => $e->getMessage()
            ]);
        }

    }
}
