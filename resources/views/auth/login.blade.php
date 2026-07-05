<!DOCTYPE html>
<html lang="en">
    <head>

        <meta charset="utf-8" />
        <title>Log In</title>
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
                                        <form method="POST" action="{{ route('login') }}" class="my-4">
                                            @csrf

                                            @if (session ('error'))
                                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                    {{ session('error') }}
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            @endif

                                            <!-- Email or Username-->
                                            <div class="form-group mb-3">
                                                <label for="login" :value="__('Email or Username')" class="form-label">Email or Username</label>
                                                <input class="form-control" type="text" name="login" :value="old('login')"
                                                required
                                                autofocus
                                                autocomplete="username" id="login" required="" placeholder="Enter your email or username">
                                                @error('login')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                
                                            <!-- Password -->
                                            <div class="form-group mb-3">
                                                <label for="password" class="form-label">Password</label>
                                                <input class="form-control" type="password" required="" id="password" name="password" placeholder="Enter your password">
                                                @error('password')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                
                                            <!-- Remember Me -->
                                            <div class="form-group d-flex mb-3">
                                                <div class="col-sm-6">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
                                                        <label class="form-check-label" for="remember_me">Remember me</label>
                                                    </div>
                                                </div>

                                                <!-- Forgot Password -->
                                                <div class="col-sm-6 text-end">
                                                    <a class='text-muted fs-14' href="{{ route('password.request') }}">Forgot password?</a>                             
                                                </div>
                                            </div>
                                            
                                            <!-- Login Button -->
                                            <div class="form-group mb-0 row">
                                                <div class="col-12">
                                                    <div class="d-grid">
                                                        <button class="btn btn-primary" type="submit"> Log In </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
    
                                        <!-- Dont have an account? Signup -->
                                        <div class="text-center text-muted mb-4">
                                            <p class="mb-0">Don't have an account ?<a class='text-primary ms-2 fw-medium' href="{{ route('register') }}">Sing up</a></p>
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
                                <h3 class="text-dark mb-3 pera-title">Hello Importer! Login to get started</h3>
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