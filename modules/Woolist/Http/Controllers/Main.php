<?php

namespace Modules\Woolist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Contact;

class Main extends Controller
{
    public function getData($force_reload = false)
    {
        $company = $this->getCompany();
        $products = app(\App\Services\Catalog\StoreProductFetcher::class)
            ->fetch($company, 'woocommerce', (bool) $force_reload);

        return array_map(fn ($product) => (object) [
            'title' => $product['title'] ?? '',
            'description' => $product['description'] ?? '',
            'image' => $product['image'] ?? null,
            'link' => $product['link'] ?? '',
        ], $products);
    }

    public function getOrders(Request $request)
    {
        //Get the contact id
        $contact_id = $request->input('contact_id');
        $contact = Contact::find($contact_id);
        if (empty($contact)) {
            return response()->json(['success' => false, 'message' => 'Contact not found']);
        }

        //Check if the contact has a email
        if (empty($contact->email)) {
            return response()->json(['success' => false, 'message' => 'Contact email not found']);
        }

        $company_id = $contact->company_id;
        $company = Company::findOrFail($company_id);

        //Find the orders of the contact from the WooCommerce API
        $orders = $this->getOrdersByEmail($contact->email, $company);

        //Check if the orders are empty
        if (empty($orders)) {
            return response()->json(['success' => false, 'message' => 'No orders found']);
        }

        //Return the orders
        return response()->json(['success' => true, 'data' => $orders]);

    }

    private function getOrdersByEmail($email, $company)
    {
        $woocommerce_store_url = $company->getConfig('woocommerce_store_url');
        $woocommerce_consumer_key = $company->getConfig('woocommerce_consumer_key');
        $woocommerce_consumer_secret = $company->getConfig('woocommerce_consumer_secret');

        //If demo mode is active, return an empty array
        if (config('settings.is_demo', false)) {
            $woocommerce_store_url = (string) config('services.woocommerce_demo.store_url');
            $woocommerce_consumer_key = (string) config('services.woocommerce_demo.consumer_key');
            $woocommerce_consumer_secret = (string) config('services.woocommerce_demo.consumer_secret');
        }

        if (empty($woocommerce_store_url) || empty($woocommerce_consumer_key) || empty($woocommerce_consumer_secret)) {
            return [];
        }

        $url = $woocommerce_store_url.'/wp-json/wc/v3/orders?consumer_key='.$woocommerce_consumer_key.'&consumer_secret='.$woocommerce_consumer_secret.'&billing_email='.$email;

        Log::info($url);

        //Remove // from the url
        $url = str_replace('//', '/', $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        //We need to filter to only orders that have the email in the billing_email field
        $orders = json_decode($response, true);
        if (! $orders) {
            return [];
        }

        $filtered_orders = array_filter($orders, function ($order) use ($email) {
            return isset($order['billing']['email']) && $order['billing']['email'] === $email;
        });

        return array_values($filtered_orders);

    }

    public function getDataFromWooCommerce(Company $company)
    {
        $woocommerce_store_url = $company->getConfig('woocommerce_store_url');
        $woocommerce_consumer_key = $company->getConfig('woocommerce_consumer_key');
        $woocommerce_consumer_secret = $company->getConfig('woocommerce_consumer_secret');
        $woocommerce_currency = $company->getConfig('woocommerce_currency', '$');

        if (config('settings.is_demo', false)) {
            $woocommerce_store_url = (string) config('services.woocommerce_demo.store_url');
            $woocommerce_consumer_key = (string) config('services.woocommerce_demo.consumer_key');
            $woocommerce_consumer_secret = (string) config('services.woocommerce_demo.consumer_secret');
        }

        if (empty($woocommerce_store_url) || empty($woocommerce_consumer_key) || empty($woocommerce_consumer_secret)) {
            return [];
        }

        $url = $woocommerce_store_url.'/wp-json/wc/v3/products?consumer_key='.$woocommerce_consumer_key.'&consumer_secret='.$woocommerce_consumer_secret;

        //Remove // from the url
        $url = str_replace('//', '/', $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $products = json_decode($response);

        //Loop through the products and get the title, the image and the price and the link
        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = [
                'title' => $product->name,
                'description' => $woocommerce_currency.$product->price,
                'image' => ! empty($product->images) ? $product->images[0]->src : null,
                'link' => $product->permalink,
            ];
        }

        //Loop through the products and check if the product has image. if yes, then set the thuumbnail version of the image as the image
        foreach ($productsData as $key => $product) {
            if (! empty($product['image'])) {
                $productsData[$key]['image'] = str_replace('.jpg', '-100x100.jpg', $product['image']);
            }
        }

        return $productsData;

    }
}
