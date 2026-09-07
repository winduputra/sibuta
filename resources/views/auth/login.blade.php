<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

    <link rel="stylesheet" href="{{ url('plugins/fontawesome-free/css/all.min.css') }}">

    <link rel="stylesheet" href="{{ url('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ url('dist/css/adminlte.min.css?v=3.2.0') }}">
</head>
<body class="hold-transition login-page ">

    <div class="login-box">

        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="{{ url('/') }}" class="h1 d-flex justify-content-center align-items-center">
                    <b>Login</b>
                </a>
            </div>
            <div class="card-body">

                @if ( $errors->any() )
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                    <p class="mb-0">{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                <form action="" method="post">
                    @csrf
                    <div class="input-group mb-3">
                        <input type="username" name="username" class="form-control" placeholder="Username" autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                        </div>

                    </div>
                </form>
                
            </div>

        </div>

    </div>

    <script src="{{ url('plugins/jquery/jquery.min.js') }}"></script>

    <script src="{{ url('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script src="{{ url('dist/js/adminlte.min.js?v=3.2.0') }}"></script>
</body>
</html>

