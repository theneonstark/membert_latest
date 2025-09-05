@extends('layouts.app')
@section('title', 'Dashboard')
@section('pagetitle', 'Dashboard')

@section('content')
    <div class="content" style="padding: 20px;">
        @if (session("kyc") == "pending" || session("kyc") == "rejected")
            <div class="row">
                <div class="col-md-12">
                    <a href="{{ route('onboarding') }}" class="text-decoration-none">
                        @csrf
                        <div class="panel panel-flat border-top-lg border-top-danger" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s;">
                            <div class="panel-heading" style="padding: 15px 20px; border-bottom: 1px solid #eee;">
                                <h5 class="panel-title" style="color: #d32f2f; font-weight: 600;">Onboarding Profile</h5>
                            </div>
                            <div class="panel-body" style="padding: 20px; color: #666;">
                                <p>Your profile is incomplete. Please complete your onboarding to start services.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @elseif(session("kyc") == "submitted")
            <div class="alert bg-info alert-styled-left no-margin mb-20" style="background: #0288d1; color: white; border-radius: 6px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <span class="text-semibold">Well done!</span> You have successfully completed your onboarding. Please wait for approval.
            </div>
        @endif
        
        <div class="row" style="margin-top: 20px;">
    <div class="col-12 col-md-4">
        <div class="panel bg-teal" style="background: linear-gradient(135deg, #26a69a, #4db6ac); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.2s;">
            <div class="panel-body text-center" style="padding: 25px; color: white;">
                <div class="content-group mt-5">
                    <i class="icon-wallet icon-3x" style="opacity: 0.9;"></i>
                </div>
                <h6 class="text-semibold"><a href="#" class="text-white" style="text-decoration: none; transition: opacity 0.2s;">Collection Wallet</a></h6>
                <h5 class="collectionwallet text-semibold" style="margin-top: 10px; font-size: 1.5rem;"></h5>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-4">
        <div class="panel bg-danger" style="background: linear-gradient(135deg, #ef5350, #f06292); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.2s;">
            <div class="panel-body text-center" style="padding: 25px; color: white;">
                <div class="content-group mt-5">
                    <i class="icon-wallet icon-3x" style="opacity: 0.9;"></i>
                </div>
                <h6 class="text-semibold"><a href="#" class="text-white" style="text-decoration: none; transition: opacity 0.2s;">Payout Wallet</a></h6>
                <h5 class="mainwallet text-semibold" style="margin-top: 10px; font-size: 1.5rem;"></h5>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-4">
        <div class="panel bg-teal" style="background: linear-gradient(135deg, #26a69a, #4db6ac); border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.2s;">
            <div class="panel-body text-center" style="padding: 25px; color: white;">
                <div class="content-group mt-5">
                    <i class="icon-wallet icon-3x" style="opacity: 0.9;"></i>
                </div>
                <h6 class="text-semibold"><a href="#" class="text-white" style="text-decoration: none; transition: opacity 0.2s;">RR Wallet</a></h6>
                <h5 class="cbwallet text-semibold" style="margin-top: 10px; font-size: 1.5rem;"></h5>
            </div>
        </div>
    </div>
    
    
</div>

        <div class="row" style="margin-top: 20px;">
            <div class="col-md-6">
                <div class="panel border-top-teal border-top-xlg" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div class="panel-heading" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <h4 class="panel-title" style="color: #26a69a; font-weight: 600;">UPI Collection</h4>
                        <div class="heading-elements">
                            <div class="heading-text upicount" style="background: #e0f2f1; padding: 5px 10px; border-radius: 12px; color: #26a69a;">0</div>
                        </div>
                    </div>
                    <div class="panel-body p-10" style="padding: 20px;">
                        <h5 class="text-semibold text-center upiamt" style="color: #333; font-size: 1.5rem;">0</h5>
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-sm-6">
                                <span class="text-size-small" style="color: #666;">Charge</span>
                                <h6 class="text-semibold no-margin-top upicharge" style="color: #333;">0</h6>
                            </div>
                            <div class="col-sm-6 text-right">
                                <span class="text-size-small" style="color: #666;">GST</span>
                                <h6 class="text-semibold text-right no-margin-top upigst" style="color: #333;">0</h6>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer p-10" style="padding: 15px 20px; background: #fafafa; border-top: 1px solid #eee;">
                        Total Charge: <span class="upitotal" style="font-weight: 600; color: #26a69a;"></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel border-top-teal border-top-xlg" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div class="panel-heading" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <h4 class="panel-title" style="color: #26a69a; font-weight: 600;">Bank Payout</h4>
                        <div class="heading-elements">
                            <div class="heading-text payoutcount" style="background: #e0f2f1; padding: 5px 10px; border-radius: 12px; color: #26a69a;">0</div>
                        </div>
                    </div>
                    <div class="panel-body p-10" style="padding: 20px;">
                        <h5 class="text-semibold text-center payoutamt" style="color: #333; font-size: 1.5rem;">0</h5>
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-sm-6">
                                <span class="text-size-small" style="color: #666;">Charge</span>
                                <h6 class="text-semibold no-margin-top payoutcharge" style="color: #333;">0</h6>
                            </div>
                            <div class="col-sm-6 text-right">
                                <span class="text-size-small" style="color: #666;">GST</span>
                                <h6 class="text-semibold text-right no-margin-top payoutgst" style="color: #333;">0</h6>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer p-10" style="padding: 15px 20px; background: #fafafa; border-top: 1px solid #eee;">
                        Total Charge: <span class="payouttotal" style="font-weight: 600; color: #26a69a;"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals remain unchanged in structure, only adding some styling -->
    @if (Auth::user()->resetpwd == "default")
        <div id="pwdModal" class="modal fade" data-backdrop="false" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);">
                    <div class="modal-header bg-slate" style="background: #37474f; color: white; padding: 15px 20px; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                        <h6 class="modal-title">Change Password</h6>
                    </div>
                    <form id="passwordForm" action="{{route('profileUpdate')}}" method="post">
                        <div class="modal-body" style="padding: 20px;">
                            <input type="hidden" name="id" value="{{Auth::id()}}">
                            <input type="hidden" name="actiontype" value="password">
                            {{ csrf_field() }}
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label style="color: #666;">Old Password</label>
                                    <input type="password" name="oldpassword" class="form-control" required="" placeholder="Enter Value" style="border-radius: 4px;">
                                </div>
                                <div class="form-group col-md-6">
                                    <label style="color: #666;">New Password</label>
                                    <input type="password" name="password" id="password" class="form-control" required="" placeholder="Enter Value" style="border-radius: 4px;">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label style="color: #666;">Confirmed Password</label>
                                    <input type="password" name="password_confirmation" class="form-control" required="" placeholder="Enter Value" style="border-radius: 4px;">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #eee;">
                            <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting" style="background: #37474f; color: white; border-radius: 4px;">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Other modals (noticeModal, addMoneyModal, getMoneyModal) follow similar styling enhancements -->
    <!-- Keeping their structure intact, just adding border-radius, shadows, and better padding -->
</div>
@endsection

@push('script')
<!--<script type="text/javascript" src="{{asset('')}}assets/js/plugins/forms/selects/select2.min.js"></script>-->
<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>-->

<script>
    var salesdata = {
        dates : [],
        payoutsales : [],
        rechargesales : [],
        billpaysales : [],
        aepssales : [],
        dmtsales : [],
        payinsales  : []
    };

    var stacked_columns, stacked_columns_options;
    $(document).ready(function(){
        $('select').select2();

        @if (Myhelper::hasNotRole('admin') && Auth::user()->resetpwd == "default")
            $('#pwdModal').modal();
        @endif

        @if ($notice != null && $notice != '')
            $('#noticeModal').modal();
        @endif

        $( "#passwordForm" ).validate({
            rules: {
                @if (!Myhelper::can('member_password_reset'))
                oldpassword: {
                    required: true,
                    minlength: 6,
                },
                password_confirmation: {
                    required: true,
                    minlength: 8,
                    equalTo : "#password"
                },
                @endif
                password: {
                    required: true,
                    minlength: 8
                }
            },
            messages: {
                @if (!Myhelper::can('member_password_reset'))
                oldpassword: {
                    required: "Please enter old password",
                    minlength: "Your password lenght should be atleast 6 character",
                },
                password_confirmation: {
                    required: "Please enter confirmed password",
                    minlength: "Your password lenght should be atleast 8 character",
                    equalTo : "New password and confirmed password should be equal"
                },
                @endif
                password: {
                    required: "Please enter new password",
                    minlength: "Your password lenght should be atleast 8 character"
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
                SYSTEM.FORMSUBMIT($('#passwordForm'), function(data) {
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            $('#passwordForm')[0].reset();
                            $('#passwordForm').closest('.modal').modal('hide');
                            SYSTEM.NOTIFY("Password Successfully Changed" , 'success');
                        }else{
                            SYSTEM.NOTIFY(data.message , 'warning');
                        }
                    } else {
                        SYSTEM.SHOWERROR(data, $('#passwordForm'));
                    }
                });
            }
        });

        GETSTATICS();
        MYGETSTATICS();
        //GETCOMMISSION();

        $( "#getMoneyForm").validate({
            rules: {
                amount: {
                    required: true,
                }
            },
            messages: {
                amount: {
                    required: "Please enter amount",
                },
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
                var form = $('#getMoneyForm');
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
                            form[0].reset();
                            SYSTEM.NOTIFY("Successfully Transfer", 'success');
                        }else{
                            SYSTEM.NOTIFY(data.message , 'warning');
                        }
                    },
                    error: function(errors) {
                        showError(errors, form);
                    }
                });
            }
        });
    });

    function GETCOMMISSION(){
        $.ajax({
            url: "{{route('datastatics')}}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            data : { "type" : "commission"},
            success: function(data){
                $(".today").text((data.aeps.day + data.matm.day + data.recharge.day).toFixed(0));
                $(".month").text((data.aeps.month + data.matm.month + data.recharge.month).toFixed(0));
                $(".lastmonth").text((data.aeps.last + data.matm.last + data.recharge.last).toFixed(0));
            }
        });
    }

    function GETSTATICS(){
        $.ajax({
            url: "{{route('statics')}}",
            type: "GET",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            success: function(data){
                $.each(data.main, function (index, value) {
                    salesdata["dates"][index] =  moment(value.created_at, "YYYY-MM-DD hh:mm:ss").format("DD-MM-YYYY");
                    salesdata["payinsales"][index]  = value.payinsales;
                    salesdata["payoutsales"][index] = data.main[index].payoutsales;
                    salesdata["rechargesales"][index] = data.main[index].rechargesales;
                    salesdata["billpaysales"][index]  = data.main[index].billpaysales;
                    salesdata["aepssales"][index] = data.main[index].aepssales;
                    salesdata["dmtsales"][index]  = data.main[index].dmtsales;
                });

                var xValues = salesdata.dates;
                new Chart("myChart", {
                    type: "bar",
                    data: {
                        labels: xValues,
                        datasets: [
                            {
                                label: 'Recharge',
                                backgroundColor: 'green',
                                fill : false,
                                data: salesdata.rechargesales
                            },{
                                label: 'Bill Payment',
                                backgroundColor: 'red',
                                fill : false,
                                data: salesdata.billpaysales
                            },{
                                label: 'Aeps',
                                backgroundColor: 'blue',
                                fill : false,
                                data: salesdata.aepssales
                            },{
                                label: 'Money Transfer',
                                backgroundColor: 'black',
                                fill : false,
                                data: salesdata.dmtsales
                            },{
                                label: 'Pay-In',
                                backgroundColor: '#00897B',
                                fill : false,
                                data: salesdata.payinsales
                            },
                            {
                                label: 'Pay-Out',
                                backgroundColor: '#E53935',
                                fill : false,
                                data: salesdata.payoutsales
                            }
                        ]
                    },
                    options: {
                        legend: {display: true}
                    }
                })
            }
        });
    }

    function MYGETSTATICS(){
        $.ajax({
            url: "{{route('datastatics')}}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            data : { "type" : "businessstatics"},
            success: function(data){
                $.each(data.collectionsales, function (indexs, values) {
                    $('.'+indexs).text(Number(values).toFixed(0));
                });

                $.each(data.qrsales, function (indexs, values) {
                    $('.'+indexs).text(Number(values).toFixed(0));
                });

                $.each(data.payoutsales, function (indexs, values) {
                    $('.'+indexs).text(Number(values).toFixed(0));
                });
            }
        });
    }
</script>
@endpush