<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GoogleTagManager;
use Toastr;

class TagManagerController extends Controller
{
    protected function normalizeGtmCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/\s+/', '', $normalized);

        return preg_replace('/^GTM-/', '', $normalized);
    }

    protected function clearTagManagerCache(): void
    {
        cache()->forget('gtm_code_list');
    }

    public function index(Request $request)
    {
        $data = GoogleTagManager::orderBy('id','DESC')->get();
        return view('backEnd.tagmanager.index',compact('data'));
    }
    
    public function create()
    {
        return view('backEnd.tagmanager.create');
    }
    
    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'status' => 'required',
        ]);
        $input = $request->all();
        $input['code'] = $this->normalizeGtmCode((string) $request->code);
        GoogleTagManager::create($input);
        $this->clearTagManagerCache();
        Toastr::success('Success','Data insert successfully');
        return redirect()->route('tagmanagers.index');
    }
    
    public function edit($id)
    {
        $edit_data = GoogleTagManager::find($id);
        return view('backEnd.tagmanager.edit',compact('edit_data'));
    }
    
    public function update(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
        ]);
        $update_data = GoogleTagManager::find($request->id);
        $input = $request->all();
        $input['code'] = $this->normalizeGtmCode((string) $request->code);
        $input['status'] = $request->status?1:0;
        $update_data->update($input);
        $this->clearTagManagerCache();

        Toastr::success('Success','Data update successfully');
        return redirect()->route('tagmanagers.index');
    }
 
    public function inactive(Request $request)
    {
        $inactive = GoogleTagManager::find($request->hidden_id);
        $inactive->status = 0;
        $inactive->save();
        $this->clearTagManagerCache();
        Toastr::success('Success','Data inactive successfully');
        return redirect()->back();
    }
    
    public function active(Request $request)
    {
        $active = GoogleTagManager::find($request->hidden_id);
        $active->status = 1;
        $active->save();
        $this->clearTagManagerCache();
        Toastr::success('Success','Data active successfully');
        return redirect()->back();
    }
    
    public function destroy(Request $request)
    {
        $delete_data = GoogleTagManager::find($request->hidden_id);
        $delete_data->delete();
        $this->clearTagManagerCache();
        Toastr::success('Success','Data delete successfully');
        return redirect()->back();
    }
}
