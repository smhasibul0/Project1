<!DOCTYPE html>
<html lang="en">
    <head>

        <meta charset="utf-8" />
        <title>Register</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="A fully functional Inventory Management and POS System"/>
        <meta name="author" content="Hasibul Hasan"/>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}">

        <!-- App css -->
        <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />

        <!-- Icons -->
        <link href="{{ asset('backend/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

        <!-- Toaster -->
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css" >

    </head>

    <body class="bg-white">
        <!-- Begin page -->
        <div class="account-page">
            <div class="container-fluid p-0">
                <div class="row align-items-center g-0">
                    <div class="col-xl-5">
                        <div class="row">
                            <div class="col-md-7 mx-auto">
                                <div class="mb-0 border-0 p-md-5 p-lg-0 p-4">
                                    <div class="mb-4 p-0">
                                        <a href="{{ url('/') }}" class="auth-logo">
                                            <img src="{{ asset('backend/assets/images/logo-dark.png') }}" alt="logo-dark" class="mx-auto" height="50" />
                                        </a>
                                    </div>
    
                                    <div class="pt-0">
                                        <form method="POST" action="{{ route('register') }}" class="my-4">
                                            @csrf

                                    <!-- Prefix + First Name (same row) -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="prefix" class="form-label">Prefix</label>
                                                <input class="form-control" type="text" name="prefix" id="prefix" required="" placeholder="Enter your prefix">
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input class="form-control" type="text" name="first_name" id="first_name" required="" placeholder="Enter your first name">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Middle Name + Last Name (same row) -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="middle_name" class="form-label">Middle Name</label>
                                                <input class="form-control" type="text" name="middle_name" id="middle_name" placeholder="Enter your middle name (optional)">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input class="form-control" type="text" name="last_name" id="last_name" required="" placeholder="Enter your last name">
                                            </div>
                                        </div>
                                    </div>

                                            <!-- Username -->
                                            <div class="form-group mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input class="form-control" type="text" name="username" id="username" required="" placeholder="Enter your username">
                                            </div>

                                            <!-- Email -->
                                            <div class="form-group mb-3">
                                                <label for="emailaddress" class="form-label">Email address</label>
                                                <input class="form-control" type="email" type="email" name="email" id="email" required="" placeholder="Enter your email">
                                            </div>
                
                                            <!-- Password -->
                                            <div class="form-group mb-3">
                                                <label for="password" class="form-label">Password</label>
                                                <input class="form-control" type="password" required="" id="password" name="password" placeholder="Enter your password">
                                            </div>

                                            <!-- Confirm Password -->
                                            <div class="form-group mb-3">
                                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                                <input class="form-control" type="password" required="" id="password_confirmation" name="password_confirmation" placeholder="Confirm your password">
                                            </div>
                
                                            <!-- Register Button -->
                                            <div class="form-group mb-0 row">
                                                <div class="col-12">
                                                    <div class="d-grid">
                                                        <button class="btn btn-primary" type="submit"> Register </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
    
                                        <!-- Already have an account? Login -->
                                        <div class="text-center text-muted mb-4">
                                            <p class="mb-0">Already have an account ?<a class='text-primary ms-2 fw-medium' href="{{ route('login') }}">Log in</a></p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <div class="col-xl-7">
                        <div class="account-page-bg p-md-5 p-4">
                            <!-- Side Text -->
                            <div class="text-center">
                                <h3 class="text-dark mb-3 pera-title">Hello There! Sign up to get started</h3>
                                <!-- Side Image -->
                                <div class="auth-image">
                                    <img src="{{ asset('backend/assets/images/authentication.svg') }}" class="mx-auto img-fluid"  alt="images">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- END wrapper -->

        <!-- Vendor -->
        <script src="{{ asset('backend/assets/libs/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/simplebar/simplebar.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/node-waves/waves.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/waypoints/lib/jquery.waypoints.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/jquery.counterup/jquery.counterup.min.js') }}"></script>
        <script src="{{ asset('backend/assets/libs/feather-icons/feather.min.js') }}"></script>

        <!-- App js-->
        <script src="{{ asset('backend/assets/js/app.js') }}"></script>

        <!-- Toaster JS -->
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        <script>
        @if(Session::has('message'))
        var type = "{{ Session::get('alert-type','info') }}"
        switch(type){
            case 'info':
            toastr.info(" {{ Session::get('message') }} ");
            break;

            case 'success':
            toastr.success(" {{ Session::get('message') }} ");
            break;

            case 'warning':
            toastr.warning(" {{ Session::get('message') }} ");
            break;

            case 'error':
            toastr.error(" {{ Session::get('message') }} ");
            break; 
        }
        @endif 
        </script>
        
    </body>
</html>