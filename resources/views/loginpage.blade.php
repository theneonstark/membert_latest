<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{asset('')}}login/fonts/icomoon/style.css">
        <link rel="stylesheet" href="{{asset('')}}login/css/owl.carousel.min.css">
        <link rel="stylesheet" href="{{asset('')}}login/css/bootstrap.min.css">
        <link rel="stylesheet" href="{{asset('')}}login/css/style.css">
        <link href="{{asset('')}}assets/js/plugins/waitMe/waitMe.css" rel="stylesheet" type="text/css">
        <link href="{{asset('')}}assets/css/jquery-confirm.min.css" rel="stylesheet" type="text/css">
        <title>Login - {{$company->name ?? ''}}</title>
        <style>
            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .content {
                padding: 40px 0;
            }
            .container {
                max-width: 1100px;
            }
            .contents {
                display: flex;
                align-items: center;
            }
            .login-card {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }
            .login-card:hover {
                transform: translateY(-5px);
            }
            h3 {
                color: #2c3e50;
                font-weight: 700;
                margin-bottom: 15px;
            }
            h3 strong {
                color: #3498db;
            }
            p.mb-4 {
                color: #7f8c8d;
                font-size: 1.1rem;
            }
            .form-group {
                position: relative;
                margin-bottom: 25px;
            }
            .form-control {
                border: none;
                border-bottom: 2px solid #ddd;
                border-radius: 0;
                padding: 10px 0;
                font-size: 1rem;
                transition: border-color 0.3s ease;
            }
            .form-control:focus {
                box-shadow: none;
                border-color: #3498db;
            }
            label {
                position: absolute;
                top: -10px;
                left: 0;
                font-size: 0.9rem;
                color: #7f8c8d;
                transition: all 0.3s ease;
            }
            .btn-primary {
                background: linear-gradient(135deg, #3498db, #2980b9);
                border: none;
                padding: 12px;
                font-weight: 600;
                border-radius: 25px;
                transition: all 0.3s ease;
            }
            .btn-primary:hover {
                background: linear-gradient(135deg, #2980b9, #3498db);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            }
            .forgot-pass {
                color: #3498db;
                font-weight: 500;
                text-decoration: none;
                transition: color 0.3s ease;
            }
            .forgot-pass:hover {
                color: #2980b9;
                text-decoration: underline;
            }
            .img-fluid {
                max-width: 90%;
                margin: 0 auto;
                display: block;
            }
            @media (max-width: 768px) {
                .login-card {
                    padding: 20px;
                }
                .img-fluid {
                    margin-bottom: 30px;
                }
            }
        </style>
    </head>
    
    <body>
        <div class="content">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6 order-md-2">
                        <img src="{{asset('')}}login/images/undraw_file_sync_ot38.svg" alt="Image" class="img-fluid" style="animation: float 3s ease-in-out infinite;">
                    </div>

                    <div class="col-md-6 contents">
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="login-card">
                                    <div class="mb-4 text-center">
                                        <h3>Enter API Partner Portal <strong>{{$company->name ?? ''}}</strong></h3>
                                        <p class="mb-4">Access the panel using your email and password.</p>
                                    </div>
                                    
                                    <form id="login" action="{{route('authCheck')}}" method="post">
                                        @csrf
                                        
                                        <div class="form-group first">
                                            <label for="mobile">Username</label>
                                            <input type="text" class="form-control" id="mobile" name="mobile" required="">
                                        </div>

                                        <div class="form-group last mb-4">
                                            <label for="password">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required="">
                                        </div>
                  
                                        <div class="d-flex mb-5 align-items-center">
                                            <span class="ml-auto"><a href="#" class="forgot-pass">Forgot Password?</a></span> 
                                        </div>

                                        <input type="submit" value="Log In" class="btn text-white btn-block btn-primary">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{asset('')}}login/js/jquery-3.3.1.min.js"></script>
        <script src="{{asset('')}}login/js/popper.min.js"></script>
        <script src="{{asset('')}}login/js/bootstrap.min.js"></script>
        <script src="{{asset('')}}login/js/main.js"></script>
        <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.validate.min.js"></script>
        <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.form.min.js"></script>
        <script type="text/javascript" src="{{asset('')}}assets/js/plugins/waitMe/waitMe.js"></script>
        <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery-confirm.min.js"></script>

        <script type="text/javascript">
            var LOGINROOT = "{{url('auth')}}", LOGINSYSTEM;

            $(document).ready(function() {
                $('#login').submit(function() {
                    var form = $('#login');
                    form.ajaxSubmit({
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        beforeSubmit: function() {                       
                            $('body').waitMe({
                                effect  : 'pulse',
                                text    : 'Please Wait',
                                bg      : "rgba(255,255,255,0.7)",
                                color   : "#000",
                                maxSize : '',
                                waitTime : -1,
                                textPos : 'vertical',
                                fontSize : '',
                                source : '',
                                onClose : function() {}
                            });
                        },
                        complete: function() {
                            $("body").waitMe("hide");
                        },
                        success: function(data) {
                            if (data.status == "TXN") {
                                form.find("[name='password']").val(null);
                                $.alert({
                                    title: 'Login',
                                    content: "Successfully Login",
                                    type: 'green',
                                });

                                setTimeout(function(){
                                    window.location.reload();
                                }, 2000);
                            }else{
                                $.alert({
                                    title: 'Oops',
                                    content: data.message,
                                    type: 'red',
                                });
                            }
                        },
                        error: function(errors) {
                            $.alert({
                                title: 'Oops',
                                content: "Something went wrong",
                                type: 'red',
                            });
                        }
                    });

                    return false;
                });
            });
        </script>
        <style>
            @keyframes float {
                0% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
                100% { transform: translateY(0px); }
            }
        </style>
    </body>
</html>