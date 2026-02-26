<?php

namespace Modules\Blog\Database\Seeds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Modules\Blog\Models\Blog;

class BlogDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

       //if project is not in demo mode, don't insert demo data
       if(!config('settings.is_demo',false)){
        return;
    }

        //Make data for 10 WhatsApp Marketing software named "WhatsBox" 
        //Later will insert them in the database
        //The migration file is in the module Blog, 
        //Use https://unsplash.com/ to get images
        //USE AI to generate the 
        

$blogs = [
    [
        'title' => 'Top Features of WhatsBox for Effective Marketing',
        'slug' => 'top-features-of-whatsbox',
        'content' => '<h2>Revolutionary Features That Set WhatsBox Apart</h2>
<p>In today\'s digital marketing landscape, WhatsBox stands as a beacon of innovation, offering unparalleled features that transform how businesses approach WhatsApp marketing:</p>

<h3>1. Advanced Automation Suite</h3>
<ul>
    <li>Smart Reply System with AI-powered response suggestions</li>
    <li>Automated campaign scheduling with timezone optimization</li>
    <li>Custom workflow builders for complex marketing sequences</li>
</ul>

<h3>2. Analytics Dashboard</h3>
<p>Our comprehensive analytics suite provides deep insights into your marketing performance:</p>
<div class="metrics-container">
    <ul>
        <li>Real-time engagement tracking</li>
        <li>Conversion funnel analysis</li>
        <li>Customer behavior patterns</li>
    </ul>
</div>

<h3>3. Integration Capabilities</h3>
<p>Seamlessly connect with your existing tools:</p>
<div class="integration-box">
    <ul>
        <li>CRM systems integration</li>
        <li>E-commerce platform synchronization</li>
        <li>API-first architecture for custom solutions</li>
    </ul>
</div>',
        'excerpt' => 'Learn about the top features of WhatsBox that can transform your marketing strategy.',
        'featured_image' => 'https://images.unsplash.com/photo-1557804506-669a67965ba0?q=80&w=1000',
        'meta_title' => 'Top Features of WhatsBox',
        'meta_description' => 'Explore the top features of WhatsBox for enhancing your WhatsApp marketing campaigns.',
        'meta_keywords' => 'WhatsBox, WhatsApp marketing, marketing software',
        'status' => 'published',
        'is_featured' => true,
    ],
    [
        'title' => 'How WhatsBox Helps Small Businesses Grow',
        'slug' => 'how-whatsbox-helps-small-businesses',
        'content' => '<article class="business-growth">
<h2>Empowering Small Businesses with WhatsBox</h2>

<div class="introduction">
    <p>Small businesses face unprecedented challenges in today\'s digital marketplace. WhatsBox provides a comprehensive solution that levels the playing field:</p>
</div>

<section class="key-benefits">
    <h3>Cost-Effective Marketing Solutions</h3>
    <div class="benefit-box">
        <p>Our platform reduces marketing costs by up to 60% compared to traditional channels:</p>
        <ul>
            <li>Pay-as-you-grow pricing model</li>
            <li>No hidden fees or long-term contracts</li>
            <li>Scalable solutions that grow with your business</li>
        </ul>
    </div>

    <h3>Customer Engagement Tools</h3>
    <div class="engagement-metrics">
        <p>Boost your customer engagement with our suite of tools:</p>
        <table class="metrics-table">
            <tr>
                <th>Feature</th>
                <th>Impact</th>
            </tr>
            <tr>
                <td>Auto-responders</td>
                <td>24/7 customer support</td>
            </tr>
            <tr>
                <td>Smart Campaigns</td>
                <td>40% higher open rates</td>
            </tr>
        </table>
    </div>
</section></article>',
        'excerpt' => 'See how WhatsBox empowers small businesses with powerful marketing tools.',
        'featured_image' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?q=80&w=1000',
        'meta_title' => 'WhatsBox for Small Businesses',
        'meta_description' => 'Learn how WhatsBox supports small businesses with cutting-edge marketing solutions.',
        'meta_keywords' => 'WhatsBox, small business, WhatsApp marketing',
        'status' => 'published',
        'is_featured' => false,
    ],
    [
        'title' => 'The Ultimate Guide to WhatsApp Marketing with WhatsBox',
        'slug' => 'ultimate-guide-to-whatsapp-marketing',
        'content' => 'WhatsBox simplifies WhatsApp marketing. This guide will show you how to maximize its potential...',
        'excerpt' => 'Master WhatsApp marketing with this comprehensive guide to WhatsBox.',
        'featured_image' => 'https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?q=80&w=1000',
        'meta_title' => 'Guide to WhatsApp Marketing',
        'meta_description' => 'A complete guide to using WhatsBox for effective WhatsApp marketing.',
        'meta_keywords' => 'WhatsBox, WhatsApp marketing, guide',
        'status' => 'published',
        'is_featured' => true,
    ],
    [
        'title' => 'Boost Customer Engagement with WhatsBox',
        'slug' => 'boost-customer-engagement-whatsbox',
        'content' => 'Customer engagement is key to success. WhatsBox offers tools to keep your audience connected...',
        'excerpt' => 'Engage your customers like never before with WhatsBox.',
        'featured_image' => 'https://images.unsplash.com/photo-1556745757-8d76bdb6984b?q=80&w=1000',
        'meta_title' => 'Boost Customer Engagement',
        'meta_description' => 'Enhance customer engagement with WhatsBox\'s advanced features.',
        'meta_keywords' => 'WhatsBox, customer engagement, marketing software',
        'status' => 'published',
        'is_featured' => false,
    ],
    [
        'title' => 'WhatsBox: The Future of WhatsApp Marketing',
        'slug' => 'whatsbox-future-of-marketing',
        'content' => 'Explore why WhatsBox is shaping the future of WhatsApp marketing with innovative features...',
        'excerpt' => 'WhatsBox is redefining the future of WhatsApp marketing.',
        'featured_image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=1000',
        'meta_title' => 'WhatsBox: Future of Marketing',
        'meta_description' => 'Discover how WhatsBox is leading the way in WhatsApp marketing innovation.',
        'meta_keywords' => 'WhatsBox, WhatsApp marketing, future',
        'status' => 'published',
        'is_featured' => true
    ],
    
        [
            'title' => '5 Reasons Why WhatsBox is a Game Changer for Marketers',
            'slug' => 'reasons-whatsbox-is-game-changer',
            'content' => 'WhatsBox is revolutionizing WhatsApp marketing with its intuitive design and powerful features...',
            'excerpt' => 'Discover why marketers are calling WhatsBox a game changer.',
            'featured_image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=1000',
            'meta_title' => '5 Reasons WhatsBox is a Game Changer',
            'meta_description' => 'Learn the top 5 reasons why WhatsBox is transforming the marketing landscape.',
            'meta_keywords' => 'WhatsBox, WhatsApp marketing, innovation',
            'status' => 'published',
            'is_featured' => false,
        ],
        [
            'title' => 'How to Automate Your WhatsApp Campaigns with WhatsBox',
            'slug' => 'automate-whatsapp-campaigns',
            'content' => 'Automation is the future of marketing, and WhatsBox makes it easier than ever...',
            'excerpt' => 'Learn how to save time by automating your WhatsApp campaigns using WhatsBox.',
            'featured_image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=1000',
            'meta_title' => 'Automate WhatsApp Campaigns',
            'meta_description' => 'Find out how WhatsBox helps you automate your WhatsApp marketing campaigns.',
            'meta_keywords' => 'WhatsBox, WhatsApp automation, campaigns',
            'status' => 'published',
            'is_featured' => true,
        ],
        [
            'title' => 'The Role of AI in WhatsApp Marketing with WhatsBox',
            'slug' => 'ai-in-whatsapp-marketing',
            'content' => 'Artificial Intelligence is transforming marketing strategies, and WhatsBox is at the forefront...',
            'excerpt' => 'Explore how AI-powered tools in WhatsBox are reshaping WhatsApp marketing.',
            'featured_image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?q=80&w=1000',
            'meta_title' => 'AI in WhatsApp Marketing',
            'meta_description' => 'Discover the role of AI in WhatsBox and how it enhances WhatsApp marketing.',
            'meta_keywords' => 'WhatsBox, AI, WhatsApp marketing',
            'status' => 'published',
            'is_featured' => false,
        ],
        [
            'title' => '10 Tips to Maximize ROI with WhatsBox',
            'slug' => 'maximize-roi-with-whatsbox',
            'content' => 'Achieving a high ROI is every marketer\'s goal. With WhatsBox, you can take your campaigns to the next level...',
            'excerpt' => 'Learn 10 practical tips to maximize your marketing ROI using WhatsBox.',
            'featured_image' => 'https://images.unsplash.com/photo-1579532537598-459ecdaf39cc?q=80&w=1000',
            'meta_title' => 'Maximize ROI with WhatsBox',
            'meta_description' => 'Learn how to maximize ROI on your WhatsApp marketing campaigns with WhatsBox.',
            'meta_keywords' => 'WhatsBox, ROI, WhatsApp marketing',
            'status' => 'published',
            'is_featured' => true,
        ],
        [
            'title' => 'WhatsBox Success Stories: Real Businesses, Real Results',
            'slug' => 'whatsbox-success-stories',
            'content' => 'From small startups to established enterprises, WhatsBox has helped businesses achieve remarkable success...',
            'excerpt' => 'Read inspiring success stories of businesses that have grown with WhatsBox.',
            'featured_image' => 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?q=80&w=1000',
            'meta_title' => 'WhatsBox Success Stories',
            'meta_description' => 'Explore real-world success stories of businesses using WhatsBox.',
            'meta_keywords' => 'WhatsBox, success stories, WhatsApp marketing',
            'status' => 'published',
            'is_featured' => false,
        ]
    ];

    //Reverse the array
    //$blogs = array_reverse($blogs);

    //Insert the data in the database
    foreach ($blogs as $blog) {
        Blog::create($blog);
    }
    

      
        Model::reguard();
    }
}

