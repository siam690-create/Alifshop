<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Campaign;
use Toastr;
use Image;
use File;
use Str;
use DB;

class CampaignController extends Controller
{
    /* =================== INDEX =================== */
    public function index()
    {
        $show_data = Campaign::orderBy('id', 'DESC')->get();
        return view('backEnd.campaign.index', compact('show_data'));
    }

    /* =================== CREATE =================== */
    public function create()
    {
        $products = Product::where('status', 1)->get();
        return view('backEnd.campaign.create', compact('products'));
    }

    /* =================== STORE =================== */
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'product_id' => 'required|array|min:1',

            // images
            'feature1_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'feature2_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'banner_image1'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'banner_image2'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image1'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image2'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image3'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image4'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image5'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image6'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image7'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image8'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

            // review ratings
            'review1_rating'  => 'nullable|integer|min:1|max:5',
            'review2_rating'  => 'nullable|integer|min:1|max:5',
            'review3_rating'  => 'nullable|integer|min:1|max:5',

            // show product toggle
            'show_product'    => 'nullable|boolean',

            // FAQ
            'faq_q1' => 'nullable|string|max:255',
            'faq_a1' => 'nullable|string',
            'faq_q2' => 'nullable|string|max:255',
            'faq_a2' => 'nullable|string',
            'faq_q3' => 'nullable|string|max:255',
            'faq_a3' => 'nullable|string',
            'faq_q4' => 'nullable|string|max:255',
            'faq_a4' => 'nullable|string',
        ]);

        $input    = $this->saveData($request);
        $campaign = Campaign::create($input);

        // ২ নম্বর থেকে শুরু করে বাকি প্রোডাক্টগুলো pivot table-এ যাবে
        if (!empty($request->product_id) && count($request->product_id) > 1) {
            $campaign->products()->attach(array_slice($request->product_id, 1));
        }

        Toastr::success('Success', 'Campaign created successfully');
        return redirect()->route('campaign.index');
    }

    /* =================== EDIT =================== */
    public function edit($id)
    {
        $edit_data = Campaign::findOrFail($id);
        $products  = Product::where('status', 1)->get();

        $selected = DB::table('campaign_product')
            ->where('campaign_id', $id)
            ->pluck('product_id')
            ->toArray();

        return view('backEnd.campaign.edit', compact('edit_data', 'products', 'selected'));
    }

    /* =================== UPDATE =================== */
    public function update(Request $request)
    {
        $request->validate([
            'hidden_id'  => 'required|exists:campaigns,id',
            'name'       => 'required|string|max:255',
            'product_id' => 'required|array|min:1',

            'feature1_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'feature2_image'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'banner_image1'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'banner_image2'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image1'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image2'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image3'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image4'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image5'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image6'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image7'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_image8'  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

            'review1_rating'  => 'nullable|integer|min:1|max:5',
            'review2_rating'  => 'nullable|integer|min:1|max:5',
            'review3_rating'  => 'nullable|integer|min:1|max:5',

            'show_product'    => 'nullable|boolean',

            'faq_q1' => 'nullable|string|max:255',
            'faq_a1' => 'nullable|string',
            'faq_q2' => 'nullable|string|max:255',
            'faq_a2' => 'nullable|string',
            'faq_q3' => 'nullable|string|max:255',
            'faq_a3' => 'nullable|string',
            'faq_q4' => 'nullable|string|max:255',
            'faq_a4' => 'nullable|string',
        ]);

        $campaign = Campaign::findOrFail($request->hidden_id);

        $input = $this->saveData($request, $campaign);
        $campaign->update($input);

        if (!empty($request->product_id) && count($request->product_id) > 1) {
            $campaign->products()->sync(array_slice($request->product_id, 1));
        } else {
            $campaign->products()->sync([]);
        }

        Toastr::success('Success', 'Campaign updated successfully');
        return redirect()->route('campaign.index');
    }

    /* =================== COMMON SAVE HANDLER =================== */
    private function saveData(Request $request, Campaign $campaign = null)
    {
        // basic input (FAQ সহ)
        $input = $request->except('product_id', 'hidden_id');

        // মেইন প্রোডাক্ট (প্রথম সিলেক্ট করা আইডি)
        $input['product_id'] = $request->product_id[0];

        // slug, status, video
        $input['slug']   = Str::slug($request->name);

        // status checkbox থাকলে 1, না থাকলে 0
        $input['status'] = $request->has('status') ? 1 : 0;

        // show_product checkbox থাকলে 1, না থাকলে 0
        $input['show_product'] = $request->has('show_product') ? 1 : 0;

        // youtube video
        $input['video']  = $this->youtubeId($request->video);

        // সব ইমেজ ফিল্ড একসাথে হ্যান্ডেল
        $imageFields = [
            'feature1_image', 'feature2_image',
            'banner_image1', 'banner_image2',
            'gallery_image1', 'gallery_image2', 'gallery_image3', 'gallery_image4',
            'gallery_image5', 'gallery_image6', 'gallery_image7', 'gallery_image8',
        ];

        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {

                // আপডেটে পুরনো ইমেজ থাকলে ডিলিট
                if ($campaign && $campaign->$field) {
                    File::delete($campaign->$field);
                }

                $file = $request->file($field);

                $name = time() . "-" . $field . "-" .
                    Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.webp';

                $path = 'public/uploads/campaign/';

                if (!is_dir($path)) {
                    @mkdir($path, 0755, true);
                }

                Image::make($file)
                    ->encode('webp', 90)
                    ->save($path . $name);

                $input[$field] = $path . $name;

            } else {
                // আপডেট কেসে পুরনো ইমেজ রেখে দাও
                if ($campaign) {
                    $input[$field] = $campaign->$field;
                }
            }
        }

        return $input;
    }

    /* =================== STATUS TOGGLE =================== */
    public function inactive(Request $request)
    {
        Campaign::where('id', $request->hidden_id)->update(['status' => 0]);
        Toastr::success('Success', 'Campaign Inactivated');
        return back();
    }

    public function active(Request $request)
    {
        Campaign::where('id', $request->hidden_id)->update(['status' => 1]);
        Toastr::success('Success', 'Campaign Activated');
        return back();
    }

    /* =================== DELETE =================== */
    public function destroy(Request $request)
    {
        $campaign = Campaign::findOrFail($request->hidden_id);

        $imageFields = [
            'feature1_image', 'feature2_image',
            'banner_image1', 'banner_image2',
            'gallery_image1', 'gallery_image2', 'gallery_image3', 'gallery_image4',
            'gallery_image5', 'gallery_image6', 'gallery_image7', 'gallery_image8',
        ];

        foreach ($imageFields as $field) {
            if ($campaign->$field) {
                File::delete($campaign->$field);
            }
        }

        // pivot relation clear
        $campaign->products()->sync([]);

        $campaign->delete();

        Toastr::success('Success', 'Campaign deleted');
        return back();
    }

    /* =================== YOUTUBE ID EXTRACT =================== */
    private function youtubeId($url)
    {
        if (!$url) return null;

        // শুধু ID দিলে
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
        return $m[1] ?? null;
    }
}
