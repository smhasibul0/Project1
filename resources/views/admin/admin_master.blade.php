<!DOCTYPE html>
<html lang="en">

    <head>

        <meta charset="utf-8" />
        <title> Admin Dashboard </title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="A fully functional Inventory Management and POS System"/>
        <meta name="author" content="Hasibul Hasan"/>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{ asset('backend/assets/images/favicon.ico') }}">

        <!-- Datatables css -->
        <link href="{{ asset('backend/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('backend/assets/libs/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('backend/assets/libs/datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('backend/assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('backend/assets/libs/datatables.net-select-bs5/css/select.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />

        <!-- App css -->
        <link href="{{ asset('backend/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />

        <!-- Icons -->
        <link href="{{ asset('backend/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        <!-- Toaster -->
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css" >

    </head>

    <!-- body start -->
    <body data-menu-color="light" data-sidebar="default">

        <!-- Begin page -->
        <div id="app-layout">


            <!-- Topbar Start -->
            @include('admin.body.header')
            <!-- end Topbar -->

            <!-- Left Sidebar Start -->
            @include('admin.body.sidebar')
            <!-- Left Sidebar End -->

            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->

            <div class="content-page">
            @yield('admin')
            <!-- content -->

            <!-- Footer Start -->
            @include('admin.body.footer')
            <!-- end Footer -->
                
            </div>
            <!-- ============================================================== -->
            <!-- End Page content -->
            <!-- ============================================================== -->

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

        <!-- Apexcharts JS -->
        <script src="{{ asset('backend/assets/libs/apexcharts/apexcharts.min.js') }}"></script>

        <!-- for basic area chart -->
        <script src="https://apexcharts.com/samples/assets/stock-prices.js"></script>

        <!-- Widgets Init Js -->
        <script src="{{ asset('backend/assets/js/pages/analytics-dashboard.init.js') }}"></script>

        <!-- Sweet Alerts js -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> 
        <script src="{{ asset('backend/assets/js/code.js') }}"></script>

        <!-- App js-->
        <script src="{{ asset('backend/assets/js/app.js') }}"></script>
        <script src="{{ asset('backend/assets/js/validate.min.js') }}"></script>

        <!-- Datatables js -->
        <script src="assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>

        <!-- dataTables.bootstrap5 -->
        <script src="assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
        <script src="assets/libs/datatables.net-buttons/js/dataTables.buttons.min.js"></script>

        <!-- buttons.colVis -->
        <script src="assets/libs/datatables.net-buttons/js/buttons.colVis.min.js"></script>
        <script src="assets/libs/datatables.net-buttons/js/buttons.flash.min.js"></script>
        <script src="assets/libs/datatables.net-buttons/js/buttons.html5.min.js"></script>
        <script src="assets/libs/datatables.net-buttons/js/buttons.print.min.js"></script>

        <!-- buttons.bootstrap5 -->
        <script src="assets/libs/datatables.net-buttons-bs5/js/buttons.bootstrap5.min.js"></script>

        <!-- dataTables.keyTable -->
        <script src="assets/libs/datatables.net-keytable/js/dataTables.keyTable.min.js"></script>
        <script src="assets/libs/datatables.net-keytable-bs5/js/keyTable.bootstrap5.min.js"></script>

        <!-- dataTable.responsive -->
        <script src="assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
        <script src="assets/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

        <!-- dataTables.select -->
        <script src="assets/libs/datatables.net-select/js/dataTables.select.min.js"></script>
        <script src="assets/libs/datatables.net-select-bs5/js/select.bootstrap5.min.js"></script>

        <!-- Datatable Demo App Js -->
        <script src="assets/js/pages/datatable.init.js"></script>

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