@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<div class="container-xxl">
    <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
        <div class="flex-grow-1">
            <h4 class="fs-18 fw-semibold m-0">Profile</h4>
        </div>

        <div class="text-end">
            <ol class="breadcrumb m-0 py-0">
                <li class="breadcrumb-item"><a href="javascript: void(0);">Components</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">

                <div class="card-body">

                    <div class="align-items-center">
                        <div class="d-flex align-items-center">
                            <img src="{{ (!empty($profileData->photo)) ? url('upload/user_images/' . $profileData->photo) : url('upload/no_image.jpg') }}" class="rounded-circle avatar-xxl img-thumbnail float-start" alt="image profile">

                            <div class="overflow-hidden ms-4">
                                <h4 class="m-0 text-dark fs-20">{{ trim($profileData->first_name . ' ' . $profileData->middle_name . ' ' . $profileData->last_name) }}</h4>
                                <p class="my-1 text-muted fs-16">{{ $profileData->email }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane pt-4" id="profile_setting" role="tabpanel">
                        <div class="row">

                            <!-- ======= Change Password ======= -->
                            <div class="col-12 mb-4">
                                <div class="card border mb-0">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <h4 class="card-title mb-0">Change Password</h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body mb-0">
                                        <form action="{{ route('admin.password.update') }}" method="post">
                                        @csrf
                                        <div class="form-group mb-3 row">
                                            <label class="form-label">Old Password</label>
                                            <div class="col-lg-12 col-xl-12">
                                                <input class="form-control @error('old_password') is-invalid @enderror" type="password" name="old_password" id="old_password" placeholder="Old Password">
                                                @error('old_password')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>  
                                        <div class="form-group mb-3 row">
                                            <label class="form-label">New Password</label>
                                            <div class="col-lg-12 col-xl-12">
                                                <input class="form-control @error('new_password') is-invalid @enderror" type="password" name="new_password" id="new_password" placeholder="New Password">
                                                @error('new_password')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group mb-3 row">
                                            <label class="form-label">Confirm Password</label>
                                            <div class="col-lg-12 col-xl-12">
                                                <input class="form-control @error('new_password_confirmation') is-invalid @enderror" type="password" name="new_password_confirmation" id="new_password_confirmation" placeholder="Confirm Password">
                                                @error('new_password_confirmation')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-lg-12 col-xl-12">
                                                <button type="submit" class="btn btn-primary">Change Password</button>
                                                <button type="button" class="btn btn-danger">Cancel</button>
                                            </div>
                                        </div>
                                        </form>
                                    </div><!--end card-body-->
                                </div>
                            </div>

                            <!-- ======= Personal Info + More Info + Bank Details ======= -->
                            <div class="col-12 mb-4">
                                <div class="card border mb-0">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <h4 class="card-title mb-0">Profile Details</h4>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <form action="{{ route('profile.store') }}" method="post" enctype="multipart/form-data">
                                        @csrf

                                            {{-- ---- Personal Information ---- --}}
                                            <h5 class="fw-semibold text-muted mb-3 border-bottom pb-2">Personal Information</h5>

                                            <div class="form-group mb-3 row">
                                                <div class="col-lg-3 col-xl-3">
                                                    <label class="form-label">Prefix</label>
                                                    <input class="form-control" type="text" name="prefix" value="{{ $profileData->prefix }}">
                                                </div>
                                                <div class="col-lg-9 col-xl-9">
                                                    <label class="form-label">First Name</label>
                                                    <input class="form-control" type="text" name="first_name" value="{{ $profileData->first_name }}">
                                                </div>
                                            </div>

                                            <div class="form-group mb-3 row">
                                                <div class="col-lg-6 col-xl-6">
                                                    <label class="form-label">Middle Name</label>
                                                    <input class="form-control" type="text" name="middle_name" value="{{ $profileData->middle_name }}">
                                                </div>
                                                <div class="col-lg-6 col-xl-6">
                                                    <label class="form-label">Last Name</label>
                                                    <input class="form-control" type="text" name="last_name" value="{{ $profileData->last_name }}">
                                                </div>
                                            </div>

                                            <div class="form-group mb-3 row">
                                                <div class="col-lg-6 col-xl-6">
                                                    <label class="form-label">Email Address</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="mdi mdi-email"></i></span>
                                                        <input type="text" class="form-control" name="email" value="{{ $profileData->email }}" placeholder="Email">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-xl-3">
                                                    <label class="form-label">Username</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="mdi mdi-account"></i></span>
                                                        <input type="text" class="form-control" name="username" value="{{ $profileData->username }}" placeholder="Username">
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-xl-3">
                                                    <label class="form-label">Contact Phone</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text"><i class="mdi mdi-phone-outline"></i></span>
                                                        <input class="form-control" type="text" name="phone" placeholder="Phone" value="{{ $profileData->phone }}">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-4 row align-items-center">
                                                
                                                <div class="col-lg-10 col-xl-6">
                                                    <label class="form-label">Profile Image</label>
                                                    <input class="form-control" type="file" name="photo" id="image">
                                                </div>
                                                <div class="col-lg-2 col-xl-2">
                                                    <img id="showImage" src="{{ (!empty($profileData->profile_photo)) ? url('upload/user_images/' . $profileData->profile_photo) : url('upload/no_image.jpg') }}" class="rounded-circle avatar-xl img-thumbnail" alt="image profile">
                                                </div>
                                            </div>

                                            {{-- ---- More Information ---- --}}
                                            <h5 class="fw-semibold text-muted mb-3 border-bottom pb-2 mt-5 pt-2">More Information</h5>

                                            <div class="form-group mb-4 row">
                                                <div class="col-lg-9 col-xl-3">
                                                    <label class="form-label">Date of Birth</label>
                                                    <input class="form-control" type="date" name="date_of_birth" 
                                                        value="{{ $profileData->date_of_birth ? \Carbon\Carbon::parse($profileData->date_of_birth)->format('Y-m-d') : '' }}">
                                                </div>
                                                <div class="col-lg-9 col-xl-3">
                                                    <label class="form-label">Gender</label>
                                                    <select class="form-select" name="gender">
                                                        <option value="">Choose...</option>
                                                        <option value="Male"   {{ $profileData->gender == 'Male'   ? 'selected' : '' }}>Male</option>
                                                        <option value="Female" {{ $profileData->gender == 'Female' ? 'selected' : '' }}>Female</option>
                                                        <option value="Other"  {{ $profileData->gender == 'Other'  ? 'selected' : '' }}>Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-9 col-xl-3">
                                                    <label class="form-label">Marital Status</label>
                                                    <select class="form-select" name="marital_status">
                                                        <option value="">Choose...</option>
                                                        <option value="Married"   {{ $profileData->marital_status == 'Married'   ? 'selected' : '' }}>Married</option>
                                                        <option value="Unmarried" {{ $profileData->marital_status == 'Unmarried' ? 'selected' : '' }}>Unmarried</option>
                                                        <option value="Divorced"  {{ $profileData->marital_status == 'Divorced'  ? 'selected' : '' }}>Divorced</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-9 col-xl-3">
                                                    <label class="form-label">Blood Group</label>
                                                    <input class="form-control" type="text" name="blood_group" value="{{ $profileData->blood_group }}">
                                                </div>
                                            </div>

                                            <div class="form-group mb-4 row">
                                                <div class="col-lg-9 col-xl-4">
                                                    <label class="form-label">Family Contact Number</label>
                                                    <input class="form-control" type="text" name="family_phone" value="{{ $profileData->family_phone }}">
                                                </div>
                                                <div class="col-lg-9 col-xl-4">
                                                    <label class="form-label">Facebook Link</label>
                                                    <input class="form-control" type="text" name="social_facebook" value="{{ $profileData->social_facebook }}">
                                                </div>
                                                <div class="col-lg-9 col-xl-4">
                                                    <label class="form-label">Social Media Others</label>
                                                    <input class="form-control" type="text" name="social_othesr" value="{{ $profileData->social_othesr }}">
                                                </div>
                                            </div>

                                            <div class="form-group mb-4 row">
                                                <div class="col-lg-12 col-xl-6">
                                                    <label class="form-label">Present Address</label>
                                                    <textarea name="present_address" class="form-control" placeholder="Present Address">{{ $profileData->present_address }}</textarea>
                                                </div>
                                                <div class="col-lg-12 col-xl-6">
                                                    <label class="form-label">Permanent Address</label>
                                                    <textarea name="permanent_address" class="form-control" placeholder="Permanent Address">{{ $profileData->permanent_address }}</textarea>
                                                </div>
                                            </div>

                                            {{-- ---- Bank Details ---- --}}
                                            <h5 class="fw-semibold text-muted mb-3 border-bottom pb-2">Bank Details</h5>

                                            <div class="form-group mb-4 row">
                                                <div class="col-lg-12 col-xl-3">
                                                    <label class="form-label">Account Holders Name</label>
                                                    <input class="form-control" type="text" name="bank_account_holder_name" value="{{ $profileData->bank_account_holder_name }}">
                                                </div>
                                                <div class="col-lg-12 col-xl-3">
                                                    <label class="form-label">Account Number</label>
                                                    <input class="form-control" type="text" name="bank_account_number" value="{{ $profileData->bank_account_number }}">
                                                </div>
                                                <div class="col-lg-12 col-xl-3">
                                                    <label class="form-label">Bank Name</label>
                                                    <input class="form-control" type="text" name="bank_name" value="{{ $profileData->bank_name }}">
                                                </div>
                                                <div class="col-lg-12 col-xl-3">
                                                    <label class="form-label">Routing Number</label>
                                                    <input class="form-control" type="text" name="bank_routing_number" value="{{ $profileData->bank_routing_number }}">
                                                </div>
                                                <div class="col-lg-12 col-xl-3">
                                                    <label class="form-label">Branch</label>
                                                    <input class="form-control" type="text" name="bank_branch" value="{{ $profileData->bank_branch }}">
                                                </div>
                                            </div>

                                            {{-- ---- Save Button ---- --}}
                                            <div class="col-lg-12 col-xl-12">
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>

                                        </form>
                                    </div><!--end card-body-->
                                </div>
                            </div>
                            <!-- ======= End Combined Card ======= -->

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<!-- Script to show image preview -->
<script type="text/javascript">
    $(document).ready(function(){
        $('#image').change(function(e){
            var reader = new FileReader();
            reader.onload = function(e){
                $('#showImage').attr('src', e.target.result);
            }
            reader.readAsDataURL(e.target.files['0']);
        });
    });
</script>

@endsection