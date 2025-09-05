@extends('layouts.app')
@section('title', ucwords($user->name) . " Profile")
@section('pagetitle', ucwords($user->name) . " Profile")

@section("bodyClass", "sidebar-opposite-visible has-detached-left")

@section('content')
    <div class="content">
        <div class="sidebar-detached">
            <div class="sidebar sidebar-default sidebar-separate">
                <div class="sidebar-content">
                    <div class="content-group">
                        <div class="panel-body bg-indigo-400 border-radius-top text-center" style="background-image: url(http://demo.interface.club/limitless/assets/images/bg.png); background-size: contain;">
                            <div class="content-group-sm">
                                <h6 class="text-semibold no-margin-bottom">
                                    {{ucfirst($user->name)}}
                                </h6>

                                <span class="display-block">{{$user->role->name}}</span>
                            </div>

                            <a href="#" class="display-inline-block content-group-sm">
                                <img src="{{session("profile")}}" class="img-circle img-responsive" alt="" style="width: 110px; height: 110px;">
                            </a>
                        </div>

                        <div class="panel no-border-top no-border-radius-top">
                            <ul class="navigation">
                                <li class="navigation-header">Navigation</li>

                                <li class="active"><a href="#profile" data-toggle="tab" class="legitRipple" aria-expanded="false"><i class="icon-chevron-right"></i> Profile Details</a></li>

                                <li class=""><a href="#kycdata" data-toggle="tab" class="legitRipple" aria-expanded="false"><i class="icon-chevron-right"></i> Kyc Details</a></li>

                                @if (Auth::id() == $user->id && Myhelper::can('password_change'))
                                    <li class=""><a href="#settings" data-toggle="tab" class="legitRipple" aria-expanded="false"><i class="icon-chevron-right"></i> Password Manager</a></li>
                                @endif

                                <li class=""><a href="#pinChange" data-toggle="tab" class="legitRipple" aria-expanded="false"><i class="icon-chevron-right"></i> Pin Manager</a></li>
                                
                                <li><a href="{{route('logout')}}" class="legitRipple"><i class="icon-switch2"></i> Log out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-detached">
            <div class="content-detached">
                <div class="tab-content">
                    <div class="tab-pane fade in active" id="profile">
                        <form id="profileForm" action="{{route('profileUpdate')}}" method="post" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <input type="hidden" name="id" value="{{$user->id}}">
                            <input type="hidden" name="actiontype" value="profile">
                            <div class="panel panel-default">
                                <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                                    <h3 class="panel-title">Personal Information</h3>
                                </div>
                                <div class="panel-body p-b-0">
                                    <div class="row">
                                        <div class="form-group col-md-4">
                                            <label>Name</label>
                                            <input type="text" class="form-control" value="{{$user->name}}" readonly="" placeholder="Enter Value">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Mobile</label>
                                            <input type="number" readonly="" value="{{$user->mobile}}" class="form-control" placeholder="Enter Value">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Email</label>
                                            <input type="email" class="form-control" value="{{$user->email}}" value="" readonly="" placeholder="Enter Value">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-4">
                                            <label>State</label>
                                            <select class="form-control select" readonly="">
                                                <option value="">Select State</option>
                                                @foreach ($state as $state)
                                                    <option value="{{$state->state}}">{{$state->state}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>City</label>
                                            <input type="text" class="form-control" value="{{$user->city}}" readonly="" placeholder="Enter Value">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Pincode</label>
                                            <input type="number" class="form-control" value="{{$user->pincode}}" readonly="" maxlength="6" minlength="6" placeholder="Enter Value">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-12">
                                            <label>Address</label>
                                            <textarea class="form-control" rows="3" readonly="" placeholder="Enter Value">{{$user->address}}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="kycdata">
                        <div class="panel panel-default">
                            <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                                <h3 class="panel-title">Kyc Data</h3>
                            </div>
                            <div class="panel-body p-b-0">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>Shop Name</label>
                                        <input type="text"  class="form-control" value="{{$user->shopname}}" readonly placeholder="Enter Value">
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>Gst Number</label>
                                        <input type="text" class="form-control" value="{{$user->gstin}}" placeholder="Enter Value">
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label>Pancard Number</label>
                                        <input type="text" class="form-control" value="{{$user->pancard}}" readonly placeholder="Enter Value">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>Adhaarcard Number</label>
                                        <input type="text" class="form-control" value="{{$user->aadharcard}}" readonly placeholder="Enter Value">
                                    </div>

                                    <div class="form-group col-md-4">
                                        @if ($user->pancardpic)
                                            <div class="thumbnail col-md-6">
                                                <a href="{{asset('public/kyc')}}/{{$user->pancardpic}}" target="_blank">
                                                    <img src="{{asset('public/kyc')}}/{{$user->pancardpic}}" alt="">
                                                    Pancard Pic
                                                </a>
                                            </div>
                                        @endif

                                        @if ($user->aadharcardpic)
                                            <div class="thumbnail col-md-6">
                                                <a href="{{asset('public/kyc')}}/{{$user->aadharcardpic}}" target="_blank">
                                                    <img src="{{asset('public/kyc')}}/{{$user->aadharcardpic}}" alt="">
                                                    Aadharcard Pic
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pinChange">
                        <form id="pinForm" action="{{route('setpin')}}" method="post" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <input type="hidden" name="id" value="{{$user->id}}">
                            <input type="hidden" name="mobile" value="{{$user->mobile}}">
                            <input type="hidden" name="gps_location">
                            <div class="panel panel-default">
                                <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                                    <h4 class="panel-title">Pin Reset</h4>
                                </div>
                                <div class="panel-body p-b-0">
                                    <div class="row">
                                        <div class="form-group col-md-4">
                                            <label>New Pin</label>
                                            <input type="password" name="pin" id="pin" class="form-control" required="" placeholder="Enter Value">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Confirmed Pin</label>
                                            <input type="password" name="pin_confirmation" class="form-control" required="" placeholder="Enter Value">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Otp</label>
                                            <input type="password" name="otp" class="form-control" Placeholder="Otp" required>
                                        </div>
                                        <span href="javascript:void(0)" onclick="OTPRESEND()" class="pull-right label bg-slate p-5 m-r-10 m-b-10">Get Otp</span>
                                    </div>
                                </div>
                                <div class="panel-footer">
                                    <button class="btn bg-slate btn-raised legitRipple pull-right" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Resetting...">Set Pin</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if (Auth::id() == $user->id && Myhelper::can('password_change'))
                        <div class="tab-pane fade" id="settings">
                            <form id="passwordForm" action="{{route('profileUpdate')}}" method="post" enctype="multipart/form-data">
                                {{ csrf_field() }}
                                <input type="hidden" name="id" value="{{$user->id}}">
                                <input type="hidden" name="actiontype" value="password">
                                <input type="hidden" name="gps_location">
                                <div class="panel panel-default">
                                    <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                                        <h3 class="panel-title pull-left">Password Reset</h3>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="panel-body p-b-0">
                                        <div class="row">
                                            <div class="form-group col-md-4">
                                                <label>Old Password</label>
                                                <input type="password" name="oldpassword" class="form-control" required="" placeholder="Enter Value">
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label>New Password</label>
                                                <input type="password" name="password" id="password" class="form-control" required="" placeholder="Enter Value">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Confirmed Password</label>
                                                <input type="password" name="password_confirmation" class="form-control" required="" placeholder="Enter Value">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="panel-footer">
                                        <button class="btn bg-slate btn-raised legitRipple pull-right" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Resetting...">Password Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
    
@push('style')
    <style type="text/css">
        .content-group .navigation li > a{
            color : #2a2a2a !important;
        }
    </style>
@endpush

@push('script')

<script type="text/javascript">
    $(document).ready(function () {
        @if($tab != "")
            $("[href='#{{$tab}}']").trigger("click");
        @endif
        
        @if (\Myhelper::hasRole('whitelable'))
            $('[name="parent_id"]').select2().val('{{$user->parent_id}}').trigger('change');
            $('[name="role_id"]').select2().val('{{$user->role_id}}').trigger('change');
        @endif

        $('[name="state"]').select2().val('{{$user->state}}').trigger('change');
        
        $( "#passwordForm").validate({
            rules: {
                oldpassword: {
                    required: true,
                },
                password_confirmation: {
                    required: true,
                    minlength: 8,
                    equalTo : "#password"
                },
                password: {
                    required: true,
                    minlength: 8,
                }
            },
            messages: {
                oldpassword: {
                    required: "Please enter old password",
                },
                password_confirmation: {
                    required: "Please enter confirmed password",
                    minlength: "Your password lenght should be atleast 8 character",
                    equalTo : "New password and confirmed password should be equal"
                },
                password: {
                    required: "Please enter new password",
                    minlength: "Your password lenght should be atleast 8 character",
                }
            },
            errorElement: "p",
            errorPlacement: function ( error, element ) {
                if ( element.prop("tagName").toLowerCase().toLowerCase() === "select" ) {
                    error.insertAfter( element.closest( ".form-group" ).find(".select2") );
                } else {
                    error.insertAfter( element );
                }
            },
            submitHandler: function () {
                var form = $('form#passwordForm');
                form.find('span.text-danger').remove();

                changePassword(form);
            }
        });

        $( "#pinForm").validate({
            rules: {
                oldpin: {
                    required: true,
                },
                pin_confirmation: {
                    required: true,
                    minlength: 6,
                    maxlength: 6,
                    equalTo : "#pin"
                },
                pin: {
                    required: true,
                    minlength: 6,
                    maxlength: 6,
                }
            },
            messages: {
                oldpin: {
                    required: "Please enter old pin",
                },
                pin_confirmation: {
                    required: "Please enter confirmed pin",
                    minlength: "Your pin lenght should be atleast 6 character",
                    maxlength: "Your pin lenght should be atleast 6 character",
                    equalTo : "New pin and confirmed pin should be equal"
                },
                pin: {
                    required: "Please enter new pin",
                    minlength: "Your pin lenght should be atleast 6 character",
                    maxlength: "Your pin lenght should be atleast 6 character",
                }
            },
            errorElement: "p",
            errorPlacement: function ( error, element ) {
                if ( element.prop("tagName").toLowerCase().toLowerCase() === "select" ) {
                    error.insertAfter( element.closest( ".form-group" ).find(".select2") );
                } else {
                    error.insertAfter( element );
                }
            },
            submitHandler: function () {
                var form = $('#pinForm');
                form.find('span.text-danger').remove();
                if (navigator.geolocation){
                    navigator.geolocation.getCurrentPosition(
                        function(position){
                            form.find("[name='gps_location']").val(position.coords.latitude+"/"+position.coords.longitude);
                            form.find("[name='pin_confirmation']").val(CryptoJSAesJson.encrypt(JSON.stringify(form.serialize()), "{{ csrf_token() }}"));
                            form.find("[name='pin']").val(CryptoJSAesJson.encrypt(form.find("[name='pin']").val(), "{{ csrf_token() }}"));
                            form.find("[name='otp']").val(CryptoJSAesJson.encrypt(JSON.stringify("otp="+form.find("[name='otp']").val()), "{{csrf_token()}}"));
                            changePin(form);
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
                                    changePin(form);
                                break;
                            }
                        }
                    );
                }
            }
        });
    });

    function OTPRESEND() {
        var mobile = "{{Auth::user()->mobile}}";
        if(mobile.length > 0){
            $.ajax({
                url: '{{ route("getotp") }}',
                type: 'post',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data :  {'mobile' : mobile},
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
                if(data.status == "TXN"){
                    SYSTEM.NOTIFY("Otp sent successfully" , 'success');
                }else{
                    SYSTEM.NOTIFY(data.message , 'warning');
                }
            })
            .fail(function() {
                SYSTEM.NOTIFY("Something went wrong, try again", 'warning');
            });
        }else{
            SYSTEM.NOTIFY("Enter your registered mobile number", 'warning');
        }
    }

    function changePassword(form) {
        form.ajaxSubmit({
            dataType:'json',
            beforeSubmit:function(){
                form.find('button:submit').button('loading');
            },
            complete: function () {
                form[0].reset();
                form.find('button:submit').button('reset');
            },
            success:function(data){
                form[0].reset();
                if(data.status == "TXN"){
                    SYSTEM.NOTIFY("Password Successfully Changed" , 'success');
                }else{
                    SYSTEM.NOTIFY(data.status , 'warning');
                }
            },
            error: function(errors) {
                form[0].reset();
                showError(errors, form.find('.panel-body'));
            }
        });
    }

    function changePin(form){
        SYSTEM.FORMSUBMIT(form, function(data){
            form[0].reset();
            if (!data.statusText) {
                if(data.status == "TXN"){
                    $.alert({
                        icon: 'fa fa-check',
                        theme: 'modern',
                        animation: 'scale',
                        type: 'green',
                        title   : "Success",
                        content : data.message
                    });
                    form.closest(".modal").modal("hide");
                }else{
                    $.alert({
                        title: 'Oops!',
                        content: data.message,
                        type: 'red'
                    });
                }
            } else {
                SYSTEM.SHOWERROR(data, form);
            }
        });
    }
</script>
@endpush
