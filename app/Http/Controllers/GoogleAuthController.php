<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Categories;
use App\Models\Products;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail; // Add this import
use App\Mail\WelcomeEmail; // Add this import
use App\Mail\NewUserRegisteredEmail; // Add this import

class GoogleAuthController extends Controller
{


    public function data_upload()
    {
        set_time_limit(0);

        // $category = new Categories;
        // $category->cat_title = "Road Construction Equipment";
        // $category->description = "--";
        // $category->type = "category";
        // $category->image = "cat_images/road-construction-equipment-logo.png";
        // $category->save();
        // $category_id = $category->_id;
        // $category_id = '67e7deec6a5af0e3790dbcd2';

        // $fileDirectory = public_path('completeData/WebsiteDataFinal/MachineryLine/excavators-Products.json');
        // $productsToInsert = [];
        // $jsonContent = file_get_contents($fileDirectory);
        // $products = json_decode($jsonContent, true);
        // foreach ($products as $product) {
        //     $productName = $product['title'] ?? null;
        //     if (!$productName || $productName == "N/A") {
        //         continue;
        //     }

        //     $images = $product['images'] ?? [];
        //     $frontImage = $images[0] ?? null;
        //     $otherImages = array_slice($images, offset: 1);
        //     $detailsArray = array_merge($product['product_details'] ?? []);
        //     $productDetails = $this->formatProductDetailsAsHtml($detailsArray);

        //     $productsToInsert[] = [
        //         'title' => $productName,
        //         'category_id' => $category_id,
        //         'price' => 0,
        //         'country' => 'China',
        //         'website' => 'agronetto',
        //         'front_image' => $frontImage,
        //         'other_images' => $otherImages,
        //         'product_details' => $productDetails,
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ];

        //     // Insert in batches of 1000 to avoid timeout
        //     if (count($productsToInsert) >= 1000) {
        //         Products::insert($productsToInsert);
        //         $productsToInsert = []; // Reset the array after each batch
        //     }
        // }

        // // Insert any remaining products
        // if (!empty($productsToInsert)) {
        //     Products::insert($productsToInsert);
        // }

        return response()->json(['message' => 'Data uploaded successfully']);
    }


    // public function data_upload()
    // {
    //     set_time_limit(0);




    //     // $product = Products::where('website' , 'one2car')->first();

    //     // if ($product && $product->front_image) {
    //     //     $savedPath = $this->saveImage($product->front_image, $product->_id); // or $product->id depending on your setup
    //     //     if ($savedPath) {
    //     //         $product->front_image = $savedPath;
    //     //         $product->save();
    //     //     }
    //     // }
    //     // dd($product);



    //     // $filePath = public_path('completeData/grouped_wagon_by_brand.json');
    //     // $jsonContent = file_get_contents($filePath);
    //     // $brands_data = json_decode($jsonContent, true);

    //     // $bodyType = 'Wagon'; // Set this based on the file type

    //     // foreach ($brands_data as $brand => $cars) {
    //     //     // Properly capitalize brand name (first letter of each word)
    //     //     $properBrandName = ucwords(strtolower($brand));

    //     //     // Check if brand exists in Categories
    //     //     $brandCategory = Categories::where('cat_title', 'like', '%' . $properBrandName . '%')
    //     //         ->where('type', 'make')
    //     //         ->first();

    //     //     // If brand doesn't exist, create it
    //     //     if (!$brandCategory) {
    //     //         $brandCategory = new Categories;
    //     //         $brandCategory->cat_title = $properBrandName;
    //     //         $brandCategory->description = "--";
    //     //         $brandCategory->type = "make";
    //     //         $brandCategory->image = null;
    //     //         // $brandCategory->save();

    //     //         echo "Created new brand category: $properBrandName\n";
    //     //     }

    //     //     $make_id = $brandCategory->_id;
    //     //     $category_id = "67fd465f7fd80f4dfb0e63c2";

    //     //     $productsToInsert = [];

    //     //     foreach ($cars as $car) {
    //     //         $images = $car['product_images'] ?? [];
    //     //         $frontImage = $images[0] ?? null;
    //     //         $otherImages = array_slice($images, 1);

    //     //         // Extract price as float
    //     //         $priceStr = $car['product_price'] ?? '0';
    //     //         $price = (float) str_replace(['Baht', ',', ' '], '', $priceStr);
    //     //         $price = $price * 0.030;
    //     //         $price = number_format($price, 2, '.', '');
    //     //         $price = (float) $price;

    //     //         // Format product details
    //     //         $productDetails = $this->formatProductDetailsAsHtml($car['specifications']);

    //     //         $productName = trim($car['product_name']);
    //     //         $product_link = $car["url"] ?? '';

    //     //         $productsToInsert[] = [
    //     //             'title' => $productName,
    //     //             'product_link' => $product_link,
    //     //             'category_id' => $category_id, // Body type category
    //     //             'make_id' => $make_id,         // Brand category
    //     //             'price' => $price,
    //     //             'country' => 'Thailand',       // Based on Baht currency
    //     //             'body_style' => $bodyType,
    //     //             'website' => 'one2car',        // Based on URL
    //     //             'front_image' => $frontImage,
    //     //             'other_images' => $otherImages,
    //     //             'product_details' => $productDetails,
    //     //             'created_at' => now(),
    //     //             'updated_at' => now(),
    //     //         ];
    //     //         if (count($productsToInsert) >= 100) {
    //     //             // Products::insert($productsToInsert);
    //     //             echo "Inserted " . count($productsToInsert) . " products for $properBrandName\n";
    //     //             $productsToInsert = [];
    //     //         }
    //     //     }
    //     //     if (!empty($productsToInsert)) {
    //     //         // Products::insert($productsToInsert);
    //     //         echo "Inserted " . count($productsToInsert) . " products for $properBrandName\n";
    //     //     }
    //     // }

    //     return response()->json(['message' => 'All products inserted successfully!']);
    // }

    private function formatProductDetailsAsHtml($details)
    {


        $html = '<ul>';
        foreach ($details as $key => $value) {
            $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }





    private function saveImage($imageUrl, $productId)
    {
        try {
            $imageContents = file_get_contents($imageUrl);
            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $fileName = $productId . '_' . Str::random(10) . '.' . $extension;
            $filePath = 'public/product_images/' . $fileName;
            Storage::disk('public')->put('product_images/' . $fileName, $imageContents);
            return 'product_images/' . $fileName;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect()
    {
        // dd(Socialite::driver('google'));
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function callback()
    {
        try {
            // Get user information from Google
            $user = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect('/')->with('error', 'Google authentication failed.');
        }

        $existing_user = User::where('email', $user->email)->first();

        if ($existing_user) {
            Auth::login($existing_user);
        } else {
            $new_user = User::updateOrCreate([
                'email' => $user->email
            ], [
                'name' => $user->name,
                'profile_picture' => $user->avatar,
                'password' => bcrypt(Str::random(16)),
                'role' => 'user',
                'email_verified_at' => now()
            ]);

            Mail::to($new_user->email)->send(new WelcomeEmail($new_user));
            Mail::to("info@suprememotors.ltd")->send(new NewUserRegisteredEmail($new_user));

            Auth::login($new_user);
        }

        return redirect(route("admin.dashboard"));
    }
}
