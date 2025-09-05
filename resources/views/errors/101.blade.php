@extends('layouts.app')
@section('title', "Password Expired")
@section('pagetitle', "Password Expired")

@section("bodyClass", "sidebar-opposite-visible has-detached-left")
@section("bodyextra", 'data-spy=scroll data-target=.sidebar-fixed')

@section('secondsidebar')
    <div class="sidebar sidebar-opposite sidebar-default">
        <div class="sidebar-fixed">
            <div class="sidebar-content">

                <div class="sidebar-category">
                    <div class="category-content no-padding">
                        <ul class="nav navigation p-t-0">
                            <li class="navigation-header"><i class="icon-history pull-right"></i> Related Links</li>
                            <li>
                                <a href="{{route('iaeps')}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> I-Aeps Service</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{route('iaadharpay')}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Aadhar Pay Service</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{route('cashdeposit')}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Cash Deposit Service </span>
                                </a>
                            </li>

                            <li>
                                <a href="{{route('dmt2')}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Money Transfer
                                </a>
                            </li>

                            <li>
                                <a href="{{route('recharge' , ['type' => 'mobile'])}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Recharge's
                                </a>
                            </li>

                            <li>
                                <a href="{{route('bill' , ['type' => 'electricity'])}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Bill Payment
                                </a>
                            </li>

                            <li>
                                <a href="{{route('pancard' , ['type' => 'uti'])}}" class="legitRipple">
                                    <i class="icon-arrow-right5"></i> Uti Pancard
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('content')
<div class="content">
    <div class="tab-content">
        <div class="tab-pane active" id="recharge">
            <div class="panel panel-default">
                <form id="passwordForm" action="{{route('profileUpdate')}}" method="post" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <input type="hidden" name="actiontype" value="password">
                    <div class="panel panel-default">
                        <div class="panel-heading">
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
        </div>
    </div>
</div>
@endsection

@push('script')

<script type="text/javascript">
    $(document).ready(function () {
        
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
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button:submit').button('loading');
                    },
                    complete: function () {
                        form.find('button:submit').button('reset');
                    },
                    success:function(data){
                        if(data.status == "success"){
                            SYSTEM.NOTIFY("Password Successfully Changed" , 'success');
                        }else{
                            SYSTEM.NOTIFY(data.status , 'warning');
                        }
                    },
                    error: function(errors) {
                        showError(errors, form.find('.panel-body'));
                    }
                });
            }
        });

    });
</script>
@endpush
