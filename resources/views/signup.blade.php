<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an Account with us  - {{$company->name ?? ''}}</title>
    <link rel="stylesheet" href="{{asset('')}}assets/loginpage/vendor/bootstrap/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/vendor/animate/animate.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/vendor/css-hamburgers/hamburgers.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/vendor/animsition/css/animsition.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/vendor/select2/select2.min.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/vendor/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/css/util.css">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/css/main.css">
    <link href="{{asset('')}}assets/css/jquery-confirm.min.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/js/plugins/materialToast/mdtoast.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{asset('')}}assets/loginpage/css/custom-style.css">
    <title>Login - {{$company->name ?? ''}}</title>

    <style type="text/css">
        p.error{
            color: #dc3545 !important;
        }

        .bg-color{
            background: #2a2a2a;
        }

        .bg-light-color{
            background: #fff;
            border-radius : 5px;
        }

        p.help {
            position: absolute;
            top: -70px;
            right: 25px;
        }

        .jconfirm .jconfirm-box div.jconfirm-content-pane{
            display: block !important;
        }
    </style>
</head>

<body>
    <div class="content bg-color">
        <div class="container-fluid">
            <div class="row justify-content-center align-items-center h-screen">
                <div class="col-md-5 contents mb-5 mb-md-0">
                    <div class="row justify-content-center align-items-center py-3 bg-light-color">
                        <div class="col-lg-10 col-md-10 mb-3">
                            <div class="text-center mb-3">
                                <img src="{{$company->logo}}" style="height: 70px;">
                            </div>
                        </div>

                        <div class="col-lg-10 col-md-10">
                            <div class="form-title">
                                <span class="title">Welcome To {{$company->companyname}}</span>
                            </div>
                            <form class="login100-form validate-form" id="registerForm" method="POST" action="{{route('web_onboard')}}" novalidate="">
                                {{ csrf_field() }}
                                <input type="hidden" name="gps_location">

                                <div class="form-group first">
                                    <label for="username">Name</label>
                                    <input type="text" class="form-control" name="name" id="cmobile" placeholder="Enter value" required="">
                                </div>

                                <div class="form-group last">
                                    <label for="password">Email Id</label>
                                    <input type="email" name="email" class="form-control" placeholder="Enter value" required="">
                                </div>

                                <div class="form-group last">
                                    <label for="password">Mobile Number</label>
                                    <input type="number" name="mobile" class="form-control" maxlength="10" minlength="10" placeholder="Enter value" required="">
                                </div>

                                <div class="data">
                                    
                                </div>

                                <input type="submit" id="cconfirm" value="Log In" class="btn btn-block btn-primary" />

                                <div class="mt-3">
                                    <p>Back To Login? 
                                        <a href="{{route("mylogin")}}" class="font-weight-bold text-black">Login</a>
                                    </p>
                                    <br>
                                    <p class=" text-primary">
                                        <strong>Need Help : </strong> <i class="fa fa-phone"></i> {{$mydata['supportnumber'] ?? ''}}
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <snackbar></snackbar>
    <script src="{{asset('')}}assets/loginpage/vendor/jquery/jquery-3.2.1.min.js"></script>
    <script src="{{asset('')}}assets/loginpage/vendor/animsition/js/animsition.min.js"></script>
    <script src="{{asset('')}}assets/loginpage/vendor/bootstrap/js/popper.js"></script>
    <script src="{{asset('')}}assets/loginpage/vendor/bootstrap/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.validate.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.form.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/sweetalert2.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery-confirm.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/materialToast/mdtoast.min.js"></script>
    <script src="{{asset('')}}assets/js/crytojs/cryptojs-aes-format.js"></script>
    <script src="{{asset('')}}assets/js/crytojs/cryptojs-aes.min.js"></script>
    <script type="text/javascript">
        var LOGINROOT = "{{url('auth')}}",
            LOGINSYSTEM;

        $(document).ready(function() {
            $.fn.extend({
                myalert: function(value, type, time = 5000) {
                    var tag = $(this);
                    tag.find('.myalert').remove();
                    tag.append('<p id="" class="myalert text-' + type + '">' + value + '</p>');
                    tag.find('input').focus();
                    tag.find('select').focus();

                    setTimeout(function() {
                        tag.find('.myalert').remove();
                    }, time);

                    tag.find('input').change(function() {
                        if (tag.find('input').val() != '') {
                            tag.find('.myalert').remove();
                        }
                    });

                    tag.find('select').change(function() {
                        if (tag.find('select').val() != '') {
                            tag.find('.myalert').remove();
                        }
                    });
                }
            });

            LOGINSYSTEM = {
                    DEFAULT: function() {
                        LOGINSYSTEM.BEFORE_SUBMIT();
                    },

                    BEFORE_SUBMIT: function() {
                        $("#registerForm").validate({
                            rules: {
                                name: {
                                    required: true,
                                },
                                mobile: {
                                    required: true,
                                    number: true
                                },
                                email: {
                                    required: true,
                                    email: true
                                }
                            },
                            messages: {
                                mobile: {
                                    required: "Please enter mobile",
                                    number: "mobile should be numeric",
                                },
                                name: {
                                    required: "Please enter your name",
                                },
                                email: {
                                    required: "Please enter your email",
                                    email: "Please enter valid email"
                                }
                            },
                            errorElement: "p",
                            errorPlacement: function(error, element) {
                                if (element.prop("tagName").toLowerCase() === "select") {
                                    error.insertAfter(element.closest(".form-group").find(".select2"));
                                } else {
                                    error.insertAfter(element);
                                }
                            },
                            submitHandler: function() {
                                LOGINSYSTEM.REGISTRATION()
                            }
                        });
                    },

                    REGISTRATION: function() {
                        SYSTEM.FORMSUBMIT($('#registerForm'), function(data) {
                            swal.close();
                            if (!data.statusText) {
                                if (data.status == "TXN") {
                                    $('#registerForm')[0].reset();
                                    $('#registerModal').modal('hide');
                                    swal({
                                        type: 'success',
                                        title: 'Success',
                                        text: 'Thank You for join us, your accont details will be sent on your mobile number and email id',
                                        showConfirmButton: true
                                    });
                                } else if (data.status == "TXNOTP") {
                                    $(".data").html(`<div class="form-group">
                                        <label for="password">Otp</label>
                                        <input type="number" name="otp" class="form-control" placeholder="Enter value" required="">
                                    </div>`);
                                } else {
                                    SYSTEM.SHOWERROR(data, $('#registerForm'));
                                }
                            } else {
                                SYSTEM.SHOWERROR(data, $('#registerForm'));
                            }
                        });
                    }
                },

                SYSTEM = {
                    NOTIFY: function(type, title, message) {
                        switch(type){
                            case "success":
                                mdtoast.success("Success : "+message, { position: "top center" });
                            break;

                            default:
                                mdtoast.error("Oops! "+message, { position: "top center" });
                                break;
                        }
                    },

                    FORMSUBMIT: function(form, callback) {
                        form.ajaxSubmit({
                            dataType: 'json',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            beforeSubmit: function() {
                                swal({
                                    title: 'Wait!',
                                    text: 'Please wait, we are working on your request',
                                    onOpen: () => {
                                        swal.showLoading()
                                    }
                                });
                            },
                            complete: function() {
                                swal.close();
                            },
                            success: function(data) {
                                callback(data);
                            },
                            error: function(errors) {
                                callback(errors);
                            }
                        });
                    },

                AJAX: function(url, method, data, callback, loading="none", msg="Updating Data"){
                    $.ajax({
                        url: url,
                        type: method,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        data: data,
                        beforeSend:function(){
                            swal({
                                title: 'Wait!',
                                text: 'Please wait, we are working on your request',
                                onOpen: () => {
                                    swal.showLoading()
                                }
                            });
                        },
                        complete: function () {
                            swal.close();
                        },
                        success:function(data){
                            callback(data);
                        },
                        error: function(errors) {
                            callback(errors);
                        }
                    });
                },

                SHOWERROR: function(errors, form, type = "inline") {
                    if (type == "inline") {
                        if (errors.statusText) {
                            if (errors.status == 422) {
                                form.find('p.error').remove();
                                $.each(errors.responseJSON, function(index, value) {
                                    form.find('[name="' + index + '"]').closest('div.form-group').myalert(value, 'danger');
                                });
                            } else if (errors.status == 400) {
                                SYSTEM.NOTIFY('error', 'Oops', errors.responseJSON.message);
                            } else {
                                SYSTEM.NOTIFY('error', 'Oops', errors.statusText);
                            }
                        } else {
                            SYSTEM.NOTIFY('error', 'Oops', errors.message);
                        }
                    } else {
                        if (errors.statusText) {
                            if (errors.status == 400) {
                                SYSTEM.NOTIFY('error', 'Oops', errors.responseJSON.message);
                            } else {
                                SYSTEM.NOTIFY('error', 'Oops', errors.statusText);
                            }
                        } else {
                            SYSTEM.NOTIFY('error', 'Oops', errors.message);
                        }
                    }
                }
            }

            LOGINSYSTEM.DEFAULT();
        });
    </script>
</body>

</html>