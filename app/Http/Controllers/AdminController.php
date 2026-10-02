<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AdminController extends Controller
{
    // Admin Logout
    public function AdminLogout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
    // End Method

    // Admin Profile
    public function AdminProfile()
    {
        $id = Auth::user()->id;
        $profileData = User::find($id);

        return view('admin.admin_profile', compact('profileData'));
    }
    // End Method

    // Admin Profile Store
    public function ProfileStore(Request $request)
    {
        $id = Auth::user()->id;
        $data = User::find($id);

        $data->username = $request->username;
        $data->prefix = $request->prefix;
        $data->first_name = $request->first_name;
        $data->middle_name = $request->middle_name;
        $data->last_name = $request->last_name;
        $data->email = $request->email;
        $data->phone = $request->phone;

        $data->date_of_birth = $request->date_of_birth;
        $data->gender = $request->gender;
        $data->marital_status = $request->marital_status;
        $data->blood_group = $request->blood_group;
        $data->family_phone = $request->family_phone;
        $data->social_facebook = $request->social_facebook;
        $data->social_othesr = $request->social_othesr;
        $data->present_address = $request->present_address;
        $data->permanent_address = $request->permanent_address;

        $data->bank_name = $request->bank_name;
        $data->bank_account_number = $request->bank_account_number;
        $data->bank_account_holder_name = $request->bank_account_holder_name;
        $data->bank_branch = $request->bank_branch;
        $data->bank_routing_number = $request->bank_routing_number;

        $oldPhotoPath = $data->photo;

        if ($request->file('photo')) {

            // Delete old image
            if ($data->photo && file_exists(public_path('upload/user_images/'.$data->photo))) {
                unlink(public_path('upload/user_images/'.$data->photo));
            }

            // Image upload
            $manager = new ImageManager(new Driver);
            $image = $request->file('photo');

            $name_gen = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();

            $img = $manager->read($image)->cover(300, 300);

            $img->save(public_path('upload/user_images/'.$name_gen));

            $data->photo = $name_gen;
        }

        $data->save();

        $notification = [
            'message' => 'Admin Profile Updated Successfully',
            'alert-type' => 'success',
        ];

        return redirect()->back()->with($notification);

    }
    // End Method

    // Admin Password Update
    public function AdminPasswordUpdate(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed|min:8',
        ]);

        if (! Hash::check($request->old_password, $user->password)) {
            $notification = [
                'message' => 'Password does not match',
                'alert-type' => 'error',
            ];

            return back()->with($notification);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        Auth::logout();

        $notification = [
            'message' => 'Password Updated Successfully',
            'alert-type' => 'success',
        ];

        return redirect()->route('login')->with($notification);
    }
    // End Method
}
