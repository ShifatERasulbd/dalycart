<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Productimage;
use App\Models\Productprice;
use App\Models\Productcolor;
use App\Models\Productsize;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Childcategory;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Size;
use Toastr;
use File;
use Str;
use Image;
use DB;

class ProductController extends Controller
{
    public function getSubcategory(Request $request)
    {
        $subcategory = DB::table("subcategories")
        ->where("category_id", $request->category_id)
        ->pluck('subcategoryName', 'id');
        return response()->json($subcategory);
    }
    public function getChildcategory(Request $request)
    {
        $childcategory = DB::table("childcategories")
        ->where("subcategory_id", $request->subcategory_id)
        ->pluck('childcategoryName', 'id');
        return response()->json($childcategory);
    }
    
    
    function __construct()
    {
         $this->middleware('permission:product-list|product-create|product-edit|product-delete', ['only' => ['index','show']]);
         $this->middleware('permission:product-create', ['only' => ['create','store']]);
         $this->middleware('permission:product-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:product-delete', ['only' => ['destroy']]);
    }
    
    
    public function index(Request $request)
    {
        if($request->keyword){
            $data = Product::orderBy('id','DESC')->where('name', 'LIKE', '%' . $request->keyword . "%")->with('image','category')->paginate(50);
        }else{
            $data = Product::orderBy('id','DESC')->with('image','category')->paginate(50);
        }
        return view('backEnd.product.index',compact('data'));
    }
    public function create()
    {
        $categories = Category::where('parent_id','=','0')->where('status',1)->select('id','name','status')->with('childrenCategories')->get();
        $brands = Brand::where('status','1')->select('id','name','status')->get();
        $colors = Color::where('status','1')->get();
        $sizes = Size::where('status','1')->get();
        return view('backEnd.product.create',compact('categories','brands','colors','sizes'));
    }
      public function store(Request $request)
    {
      
      
        $product_id = Product::insertGetId([
            'name' => $request->name,
            'name_bn'=>$request->name_bn,
            'category_id' => $request->category_id,
            'name_bn' => $request->name_bn,
            'category_id' => $request->category_id,
            'slug' => strtolower(str_replace(' ', '-', $request->name)),
            'new_price'=>$request->new_price,
            'subcategory_id' => $request->subcategory_id,
            'childcategory_id' => $request->childcategory_id,
            'brand_id' => $request->brand_id,
            'product_code' => $request->product_code,
            

            'purchase_price' => $request->purchase_price,
            'old_price' => $request->old_price,
            'new_price' => $request->new_price,
            'stock' => $request->stock,

            'pro_unit' => $request->pro_unit,
            'pro_video' => $request->pro_video,
            'description' => $request->description,
            'description_bn'=>$request->description_bn,
            'status' => $request->status,
            
        ]);
        // dd($product_id);
        $request->validate([
        
        'variations.*.color' => 'required|exists:colors,id',
        'variations.*.size' => 'required|exists:sizes,id',
        'variations.*.price' => 'required|numeric|min:0',
        'variations.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    $uploadPath = public_path('uploads/product/');

    // Ensure directory exists
    if (!File::exists($uploadPath)) {
        File::makeDirectory($uploadPath, 0755, true);
    }

    foreach ($request->variations as $variation) {
        $imagePath = null;

        if (isset($variation['image']) && $variation['image']) {
            $imageName = time() . '_' . uniqid() . '.' . $variation['image']->getClientOriginalExtension();
             $variation['image']->move($uploadPath, $imageName);
             $imagePath = 'public/uploads/product/' . $imageName;
        }
        // dd($variation['color']);
        ProductImage::create([
            'product_id' => $product_id,
            'color' => $variation['color'],
            'size' => $variation['size'],
            'price' => $variation['price'],
            'image' => $imagePath,
        ]);
    }

    return redirect()->route('products.index');
}
    public function edit($id)
{
    $product = Product::findOrFail($id);
    $product = Product::with('category','subcategory','childcategory','brand')->findOrFail($id);
    // getting the values from productImages
    // return $product->productimages->pluck('image')->toArray();
    $product_images=Productimage::where('product_id',$id)->get();
    // return ;
    $categories = Category::all();
    $subcategories = Subcategory::all();
    $brands = Brand::all();
    $colors = Color::all();
    $sizes = Size::all(); // Optional

    return view('backEnd.product.edit', compact('product', 'categories', 'subcategories', 'brands', 'colors', 'sizes','product_images'));
}
    
    public function update(Request $request, $id)
{
    
    // Find the product
    $product = Product::findOrFail($id);
    
    // Update product main fields
    $product->update([
        'name' => $request->name,
        'name_bn' => $request->name_bn,
        'category_id' => $request->category_id,
        'slug' => strtolower(str_replace(' ', '-', $request->name)),
        'subcategory_id' => $request->subcategory_id,
        'childcategory_id' => $request->childcategory_id,
        'brand_id' => $request->brand_id,
        'purchase_price' => $request->purchase_price,
        'old_price' => $request->old_price,
        'new_price' => $request->new_price,
        'stock' => $request->stock,
        'pro_unit' => $request->pro_unit,
        'pro_video' => $request->pro_video,
        'description' => $request->description,
        'description_bn' => $request->description_bn,
        'status' => $request->status ? 1 : 0,
        'topsale' => $request->topsale ? 1 : 0,
    ]);

     $uploadPath = public_path('uploads/product/');
    if (!File::exists($uploadPath)) {
        File::makeDirectory($uploadPath, 0755, true);
    }

    // Collect existing variation IDs sent in the form
     $variationIdsInRequest = collect($request->variations)->pluck('id')->filter()->toArray();

    // Delete variations that are NOT in the request (removed by user)
    ProductImage::where('product_id', $product->id)
                ->whereNotIn('id', $variationIdsInRequest)
                ->delete();

    foreach ($request->variations as $variation) {
        $imagePath = null;

        if (isset($variation['image']) && $variation['image']) {
            $imageName = time() . '_' . uniqid() . '.' . $variation['image']->getClientOriginalExtension();
             $variation['image']->move($uploadPath, $imageName);
             $imagePath = 'public/uploads/product/' . $imageName;
        }
        // dd($variation['color']);
        // return  $variation['image'];
        ProductImage::create([
            'product_id' => $id,
            'color' => $variation['color'],
            'size' => $variation['size'],
            'price' => $variation['price'],
            'image' => $imagePath,
        ]);
    }


    return redirect()->route('products.index')->with('success', 'Product updated successfully.');
}


    public function copy($id)
{
    $product = Product::findOrFail($id);
    $product = Product::with('category','subcategory','childcategory','brand')->findOrFail($id);
    // getting the values from productImages
    // return $product->productimages->pluck('image')->toArray();
    $product_images=Productimage::where('product_id',$id)->get();
    // return ;
    $categories = Category::all();
    $subcategories = Subcategory::all();
    $brands = Brand::all();
    $colors = Color::all();
    $sizes = Size::all(); // Optional

    return view('backEnd.product.copy', compact('product', 'categories', 'subcategories', 'brands', 'colors', 'sizes','product_images'));
}


    public function inactive(Request $request)
    {
        $inactive = Product::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        Toastr::success('Success','Data inactive successfully');
        return redirect()->back();
    }
    public function active(Request $request)
    {
        $active = Product::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        Toastr::success('Success','Data active successfully');
        return redirect()->back();
    }
    public function destroy(Request $request)
    {
        $delete_data = Product::find($request->hidden_id);
        $delete_data->delete();
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    }
    public function imgdestroy(Request $request)
    { 
        $delete_data = Productimage::find($request->id);
        File::delete($delete_data->image);
        $delete_data->delete();
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    } 
    public function pricedestroy(Request $request)
    { 
        $delete_data = Productprice::find($request->id);
        $delete_data->delete();
        Toastr::success('Success','Product price delete successfully');
        return redirect()->back();
    }
    public function update_deals(Request $request){
        $products = Product::whereIn('id', $request->input('product_ids'))->update(['topsale' => $request->status]);
        return response()->json(['status'=>'success','message'=>'Hot deals product status change']);
    }
    public function update_feature(Request $request){
        $products = Product::whereIn('id', $request->input('product_ids'))->update(['feature_product' => $request->status]);
        return response()->json(['status'=>'success','message'=>'Feature product status change']);
    }
    public function update_status(Request $request){
        $products = Product::whereIn('id', $request->input('product_ids'))->update(['status' => $request->status]);
        return response()->json(['status'=>'success','message'=>'Product status change successfully']);
    }
}
