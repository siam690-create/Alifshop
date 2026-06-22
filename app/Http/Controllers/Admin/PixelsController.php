<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EcomPixel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Toastr;
class PixelsController extends Controller
{
     public function index(Request $request)
    {
        $data =EcomPixel::orderBy('id','DESC')->get();
        return view('backEnd.pixels.index',compact('data'));
    }
    public function create()
    {
        return view('backEnd.pixels.create');
    }
    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string|max:255',
        ]);
        EcomPixel::create($this->pixelPayload($request));
        Cache::forget('pixels_list');
        Toastr::success('Success','Data insert successfully');
        return redirect()->route('pixels.index');
    }
    
    public function edit($id)
    {
        $edit_data =EcomPixel::find($id);
        return view('backEnd.pixels.edit',compact('edit_data'));
    }
    
    public function update(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string|max:255',
        ]);
        $update_data = EcomPixel::findOrFail($request->hidden_id);
        $update_data->update($this->pixelPayload($request));
        Cache::forget('pixels_list');

        Toastr::success('Success','Data update successfully');
        return redirect()->route('pixels.index');
    }
 
    public function inactive(Request $request)
    {
        $inactive =EcomPixel::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        Cache::forget('pixels_list');
        Toastr::success('Success','Data inactive successfully');
        return redirect()->back();
    }
    public function active(Request $request)
    {
        $active =EcomPixel::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        Cache::forget('pixels_list');
        Toastr::success('Success','Data active successfully');
        return redirect()->back();
    }
    public function destroy(Request $request)
    {
        $delete_data =EcomPixel::find($request->hidden_id);
        $delete_data->delete();
        Cache::forget('pixels_list');
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    }

    protected function pixelPayload(Request $request): array
    {
        $payload = [
            'code' => $request->code,
            'status' => $request->has('status') ? 1 : 0,
        ];

        if (Schema::hasColumn('ecom_pixels', 'browser_tracking_enabled')) {
            $payload['browser_tracking_enabled'] = $request->has('browser_tracking_enabled') ? 1 : 0;
        }

        return $payload;
    }
}
