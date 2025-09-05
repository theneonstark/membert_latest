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
    <title>Login - {{$company->name ?? ''}}</title>
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
    <link href="{{asset('')}}assets/plugin/waitMe/waitMe.css" rel="stylesheet" type="text/css">
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
    <div class="content">
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
                                <span class="title">{{$company->companyname}}</span>
                            </div>
                            <form class="login100-form validate-form" id="login" method="POST" action="{{route('authCheck')}}" novalidate="">
                                {{ csrf_field() }}
                                <input type="hidden" name="gps_location">
                                <div class="form-group first">
                                    <label for="username">Username</label>
                                    <input type="email" class="form-control" name="mobile" value="{{ old('mobile') }}" id="cmobile" placeholder="Enter username">
                                </div>
                                <div class="form-group last">
                                    <label for="password">Password</label>
                                    <input type="password" id=" password" name="password" class="form-control" id="password" placeholder="Enter password">
                                </div>

                                <div class="d-flex mb-4 align-items-center justify-content-between">
                                    <div>
                                        {{-- <label class="control control--checkbox mb-0"><span class="caption">Remember me</span>
                                            <input type="checkbox" checked="checked" />
                                            <div class="control__indicator"></div>
                                        </label> --}}
                                    </div>
                                    <div>
                                        <a href="#" class="font-weight-bold text-black" id="authReset">Forgot Password</a>
                                    </div>
                                </div>

                                <input type="submit" id="cconfirm" value="Log In" class="btn btn-block btn-primary" />

                                <div class="mt-3">
                                    <p>Not Register? 
                                        <a href="{{route("signup")}}" class="font-weight-bold text-black">Create an account</a>
                                    </p>
                                    <br>
                                    <p class=" text-primary text-center">
                                        <strong>Support <br></strong> <i class="fa fa-phone"></i> {{$mydata['supportnumber'] ?? ''}}
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title pull-left">Password Reset</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert bg-success alert-styled-left">
                        <button type="button" class="close" data-dismiss="alert"><span>×</span><span class="sr-only">Close</span></button>
                        <span class="text-semibold">Success!</span> Your password reset token successfully sent on your registered e-mail id & Mobile number.
                    </div>
                    <form id="passwordForm" action="{{route('authReset')}}" method="post">
                        <b><p class="text-danger"></p></b>
                        <input type="hidden" name="mobile">
                        <input type="hidden" name="type" value="reset">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label>Reset Token</label>
                            <input type="text" name="token" class="form-control" placeholder="Enter OTP" required="">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter New Password" required="">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-primary btn-block text-uppercase waves-effect waves-light" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Resetting">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
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
    <script type="text/javascript" src="{{asset('')}}assets/plugin/waitMe/waitMe.js"></script>
    
    <script type="text/javascript">
        var LOGINROOT = "{{url('auth')}}",
            LOGINSYSTEM;

        function getLocation(){
            if (navigator.geolocation){   
                navigator.geolocation.getCurrentPosition(showPosition,showError);
            }
        }

        function showPosition(position){
            var lat = position.coords.latitude;
            var lon = position.coords.longitude;
            console.log("lat :" + lat);
            console.log("lon :" + lon);
        }

        function showError(error){
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    swal({
                        type: 'error',
                        title : 'Location Access Denied',
                        text: 'Kindly allow permission to access location for secure browsing',
                    });
                break;

                case error.POSITION_UNAVAILABLE:
                    swal({
                        type: 'error',
                        title : 'error',
                        text: 'Permission Denied',
                    });
                    break;

                case error.TIMEOUT:
                    swal({
                        type: 'error',
                        title : 'error',
                        text: 'Permission Denied',
                    });
                    break;

                case error.UNKNOWN_ERROR:
                    swal({
                        type: 'error',
                        title : 'error',
                        text: 'Permission Denied',
                    });
                    break;
            }
        }
        
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
                },

                mynotify: function(value, type, time = 5000) {
                    var tag = $(this);
                    tag.find('.mynotify').remove();
                    tag.prepend(`<div class="mynotify alert alert-` + type + ` alert-dismissable">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        ` + value + `
                    </div>`);
                    setTimeout(function() {
                        tag.find('.mynotify').remove();
                    }, time);
                }
            });

            LOGINSYSTEM = {
                    DEFAULT: function() {
                        LOGINSYSTEM.BEFORE_SUBMIT();
                        LOGINSYSTEM.PASSWORDRESET();
                    },

                    BEFORE_SUBMIT: function() {
                        $('#login').submit(function() {
                            var username = $("[name='mobile']").val();
                            var password = $("[name='password']").val();

                            if (username == "") {
                                $("[name='mobile']").closest('.form-group').myalert('Enter username', 'danger');
                            }else if (password == "") {
                                $("[name='password']").closest('.form-group').myalert('Enter Password', 'danger');
                            } else {
                                var form = $('#login');
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

                                if (navigator.geolocation){
                                    navigator.geolocation.getCurrentPosition(
                                        function(position){
                                            form.find("[name='gps_location']").val(position.coords.latitude+"/"+position.coords.longitude);
                                            localStorage.setItem("gps_location", position.coords.latitude+"/"+position.coords.longitude);
                                            form.find("[name='password']").val(CryptoJSAesJson.encrypt(JSON.stringify(form.serialize()), "{{ csrf_token() }}"));
                                            LOGINSYSTEM.LOGIN();
                                        },function(error){
                                            switch(error.code) {
                                                case error.PERMISSION_DENIED:
                                                    swal({
                                                        type  : 'error',
                                                        title : 'Location Access Denied',
                                                        text  : 'Kindly allow permission to access location for secure browsing',
                                                    });
                                                    return false;
                                                break;

                                                default:
                                                    LOGINSYSTEM.LOGIN();
                                                break;
                                            }
                                        }
                                    );
                                }
                            }

                            return false;
                        });

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

                        $( "#passwordForm" ).validate({
                            rules: {
                                token: {
                                    required: true,
                                    number : true
                                },
                                password: {
                                    required: true,
                                }
                            },
                            messages: {
                                token: {
                                    required: "Please enter reset token",
                                    number: "Reset token should be numeric",
                                },
                                password: {
                                    required: "Please enter password",
                                }
                            },
                            errorElement: "p",
                            errorPlacement: function ( error, element ) {
                                if ( element.prop("tagName").toLowerCase() === "select" ) {
                                    error.insertAfter( element.closest( ".form-group" ).find(".select2") );
                                } else {
                                    error.insertAfter( element );
                                }
                            },
                            submitHandler: function () {
                                var form = $('#passwordForm');
                                form.ajaxSubmit({
                                    dataType:'json',
                                    beforeSubmit:function(){
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
                                    success:function(data){
                                        $("body").waitMe("hide");
                                        if(data.status == "TXN"){
                                            $('#passwordModal').modal('hide');
                                            swal({
                                                type: 'success',
                                                title: 'Reset!',
                                                text: 'Password Successfully Changed',
                                                showConfirmButton: true
                                            });
                                        }else{
                                            SYSTEM.NOTIFY(data.message, 'warning');
                                        }
                                    },
                                    error: function(errors) {
                                        $("body").waitMe("hide");
                                        if(errors.status == '400'){
                                            SYSTEM.NOTIFY(errors.responseJSON.status, 'warning');
                                        }else{
                                            SYSTEM.NOTIFY('Something went wrong, try again later.', 'warning');
                                        }
                                    }
                                });
                            }
                        });
                    },

                    LOGIN: function() {
                        var form = $('#login');
                        SYSTEM.FORMSUBMIT($('#login'), function(data) {
                            $("body").waitMe("hide");
                            if (!data.statusText) {
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
                                } else if(data.status == "TXNOTP"){
                                    var otpConfirm = $.confirm({
                                        lazyOpen: true,
                                        title: 'Otp Verification',
                                        content: '' +
                                        '<form action="javascript:void(0)" id="otpValidateForm">' +
                                        '<div class="form-group">' +
                                        '<input type="password" placeholder="Enter Otp" name="otp" class="name form-control" required />' +
                                        '</div>' +
                                        '<p class="text-success"><b>'+data.message+'</b></p>'+
                                        '</form>',
                                        buttons: {
                                            formSubmit: {
                                                text: 'Submit',
                                                keys: ['enter', 'shift'],
                                                btnClass: 'btn-blue',
                                                action: function () {
                                                    var otp      = this.$content.find('[name="otp"]').val();
                                                    var mobile   = $('#login').find('[name="mobile"]').val();
                                                    var password = $('#login').find('[name="password"]').val();
                                                    if(!otp){
                                                        $.alert({
                                                            title: 'Oops!',
                                                            content: 'Provide a valid otp',
                                                            type: 'red'
                                                        });
                                                        return false;
                                                    }
                                                    otpConfirm.close();
                                                    var data = { 
                                                        "_token" : "{{csrf_token()}}", 
                                                        "mobile" : mobile, 
                                                        "otp" : CryptoJSAesJson.encrypt(JSON.stringify("otp="+otp), "{{csrf_token()}}"), 
                                                        "password" : password,
                                                        "gps_location" : localStorage.getItem("gps_location")
                                                    };

                                                    form.find("[name='password']").val(null);
                                                    SYSTEM.AJAX("{{route('authCheck')}}", "POST", data, function(data){
                                                        if(!data.statusText){
                                                            if(data.status == "TXN"){
                                                                form.find("[name='password']").val(null);
                                                                $.alert({
                                                                    title: 'Login',
                                                                    content: "Successfully Login",
                                                                    type: 'green'
                                                                });

                                                                setTimeout(function(){
                                                                    window.location.reload();
                                                                }, 2000);
                                                            }else{
                                                                if(data.status == 400){
                                                                    $.alert({
                                                                        title: 'Oops!',
                                                                        content: data.responseJSON.message,
                                                                        type: 'red'
                                                                    });
                                                                }else{
                                                                    if(data.message){
                                                                        $.alert({
                                                                            title: 'Oops!',
                                                                            content: data.message,
                                                                            type: 'red'
                                                                        });
                                                                    }else{
                                                                        $.alert({
                                                                            title: 'Oops!',
                                                                            content: data.statusText,
                                                                            type: 'red'
                                                                        });
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    });
                                                    return false;
                                                }
                                            },
                                            cancel: function () {
                                                form.find("[name='password']").val(null);
                                            },
                                            'Resend Otp': function () {
                                                OTPRESEND();
                                                return false;
                                            },
                                        }
                                    });  
                                    otpConfirm.open();
                                }else {
                                    form.find("[name='password']").val(null);
                                    SYSTEM.SHOWERROR(data, $('#login'));
                                }
                            } else {
                                    form.find("[name='password']").val(null);
                                SYSTEM.SHOWERROR(data, $('#login'));
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
                                } else {
                                    SYSTEM.SHOWERROR(data, $('#registerForm'));
                                }
                            } else {
                                SYSTEM.SHOWERROR(data, $('#registerForm'));
                            }
                        });
                    },

                    PASSWORDRESET: function() {
                        $('#authReset').click(function() {
                            var mobile = $('input[name="mobile"]').val();
                            var ele = $(this);
                            if (mobile.length > 0) {
                                $.ajax({
                                        url: '{{ route("authReset") }}',
                                        type: 'post',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        beforeSend: function() {
                                            swal({
                                                title: 'Wait!',
                                                text: 'Please wait, we are working on your request',
                                                onOpen: () => {
                                                    swal.showLoading()
                                                }
                                            });
                                        },
                                        data: {
                                            'type': 'request',
                                            'mobile': mobile
                                        },
                                        complete: function() {
                                            swal.close();
                                        }
                                    })
                                    .done(function(data) {
                                        swal.close();
                                        if (data.status == "TXN") {
                                            $('#passwordForm').find('input[name="mobile"]').val(mobile);
                                            $('#passwordModal').modal('show');
                                        } else {
                                            SYSTEM.NOTIFY(data.message, 'warning');
                                        }
                                    })
                                    .fail(function() {
                                        swal.close();
                                        SYSTEM.NOTIFY('Something went wrong, try again', 'warning');
                                    });
                            } else {
                                SYSTEM.NOTIFY('Enter mobile number to reset password', 'warning');
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
                            },
                            complete: function() {
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

        function OTPRESEND() {
            var mobile = $('input[name="mobile"]').val();
            var password = $('input[name="password"]').val();
            if(mobile.length > 0){
                $.ajax({
                    url: '{{ route("authCheck") }}',
                    type: 'post',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data :  {'mobile' : mobile, 'password' : password , 'otp' : "resend", "_token" : "{{csrf_token()}}"},
                    beforeSend:function(){
                        swal({
                            title: 'Wait!',
                            text: 'Please wait, we are working on your request',
                            onOpen: () => {
                                swal.showLoading()
                            }
                        });
                    },
                    complete: function(){
                        swal.close();
                    }
                })
                .done(function(data) {
                    if(data.status == "TXNOTP"){
                        $.alert({
                            title: 'Login',
                            content: "Otp sent successfully",
                            type: 'green'
                        });
                    }else{
                        $.alert({
                            title: 'Oops!',
                            content: data.message,
                            type: 'red'
                        });
                    }
                })
                .fail(function() {
                    $.alert({
                        title: 'Oops!',
                        content: "Something went wrong, try again",
                        type: 'red'
                    });
                });
            }else{
                $.alert({
                    title: 'Oops!',
                    content: "Enter your registered mobile number",
                    type: 'red'
                });
            }
        }
    </script>
</body>

</html>