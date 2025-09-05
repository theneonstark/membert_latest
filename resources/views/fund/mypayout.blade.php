@extends('layouts.app')
@section('title', "My Payout")
@section('pagetitle',  "My Payout")

@php
    $search    = "yes";
    $table     = "yes";
    $newexport = "yes";
    $status['type'] = "Fund";
    $status['data'] = [
        "success"  => "Success",
        "pending"  => "Pending",
        "failed"   => "Failed",
        "reversed" => "Reversed",
        "refunded" => "Refunded"
    ];

    $product['type'] = "Transaction";
    $product['data'] = [
        "wallet" => "Move To Wallet",
        "bank" => "Move To Bank"
    ];
@endphp

@section('content')
    <div class="content">
        <div class="row">
            <a href="javascript:void(0)" data-toggle="modal" data-target="#fundRequestModal" class="payout-card">
    <div class="col-md-3 col-sm-6">
        <div class="card text-white shadow-lg border-0" style="background: linear-gradient(135deg, #01b3a2, #038584); padding: 20px; margin-bottom: 20px;">
            <div class="card-body text-center">
                <div class="icon-box mb-3">
                    <i class="fas fa-wallet"></i>
                </div>
                <h5 class="card-title fw-bold">Initiate Payout</h5>
                <p class="card-text text-white">Send money instantly to bank accounts.</p>
            </div>
            <div class="card-footer text-center">
                <span class="fw-bold">
                    Click here to make a request <i class="fa fa-long-arrow-right mr-10"></i>
                </span>
            </div>
        </div>
    </div>
</a>

            <!--<a href="javascript:void(0)" data-toggle="modal" data-target="#upiModal">-->
            <!--    <div class="col-sm-3">-->
            <!--        <div class="panel border-left-lg border-left-primary invoice-grid timeline-content">-->
            <!--            <div class="panel-heading">-->
            <!--                <h5 class="panel-title">Initiate Upi Payout</h5>-->
            <!--            </div>-->

            <!--            <div class="panel-body p-t-0">-->
            <!--                <div class="row">-->
            <!--                    <div class="col-sm-12">-->
            <!--                        <ul class="list list-unstyled">-->
            <!--                            <li><span>Send money instantly to upi account.</span></li>-->
            <!--                        </ul>-->
            <!--                    </div>-->
            <!--                </div>-->
            <!--            </div>-->
            <!--            <div class="panel-footer panel-footer-condensed">-->
            <!--                <div class="heading-elements">-->
            <!--                    <span class="heading-text no-margin-left">-->
            <!--                        <i class="fa fa-long-arrow-right mr-10"></i><span class="text-semibold">Click here to make request </span>-->
            <!--                    </span>-->
            <!--                </div>-->
            <!--            </div>-->
            <!--        </div>-->
            <!--    </div>-->
            <!--</a>-->
        </div>

        <div class="row">
            <div class="col-sm-12">
                @include('layouts.filter')
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">My Payout</h4>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th width="160px">#</th>
                                <th>User Details</th>
                                <th>Bank Details</th>
                                <th>Reference Details</th>
                                <th width="200px">Description</th>
                                <th>Remark</th>
                                <th width="100px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="fundRequestModal" class="modal fade right" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius: 15px;">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Single Payout</h6>
                </div>

                <form id="fundRequestForm" action="{{route('payouttransaction')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="type" value="mypayout">
                    <input type="hidden" name="gps_location">
                    <input type="hidden" name="pin">
                    <div class="modal-body">
                        <legend>Bank Account Details</legend>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Account Holder Name</label>
                                <input type="text" class="form-control" {{(\Auth::user()->accountname) ? "readonly=''" : ""}} value="{{\Auth::user()->accountname}}" name="name" placeholder="Enter Value">
                            </div>

                            <div class="form-group col-md-6">
                                <label>Account Number</label>
                                <input type="text" class="form-control" {{(\Auth::user()->account) ? "readonly=''" : ""}} value="{{\Auth::user()->account}}" name="account" placeholder="Enter Value">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Bank Name</label>
                                <input type="text" class="form-control" {{(\Auth::user()->bank) ? "readonly=''" : ""}} value="{{\Auth::user()->bank}}" name="bank" placeholder="Enter Value">
                            </div>

                            <div class="form-group col-md-6">
                                <label>Ifsc Code</label>
                                <input type="text" class="form-control" {{(\Auth::user()->ifsc) ? "readonly=''" : ""}} value="{{\Auth::user()->ifsc}}" name="ifsc" placeholder="Enter Value">
                            </div>
                        </div>

                        <legend>Transfer Details</legend>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Amount</label>
                                <input type="number" class="form-control" name="amount" placeholder="Enter Value" required="">
                            </div>

                            <div class="form-group col-md-12">
                                <label class="display-block text-semibold">Mode</label>
                                <label class="radio-inline">
                                    <input type="radio" name="mode" value="IMPS" class="styled">
                                    IMPS
                                </label>

                                <label class="radio-inline">
                                    <input type="radio" name="mode" value="NEFT" class="styled">
                                    NEFT
                                </label>

                                <label class="radio-inline">
                                    <input type="radio" name="mode" value="RTGS" class="styled">
                                    RTGS
                                </label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Remark (Optional)</label>
                                <input type="text" class="form-control" name="remark" placeholder="Enter Value" >
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer text-center">
                        <button class="btn bg-slate btn-raised btn-lg legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="upiModal" class="modal fade right" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Upi Payout</h6>
                </div>

                <form id="upiRequestForm" action="{{route('payouttransaction')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="type" value="upipayout">
                    <input type="hidden" name="gps_location">
                    <input type="hidden" name="pin">
                    <div class="modal-body">
                        <legend>Upi Details</legend>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Account Holder Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Value">
                            </div>

                            <div class="form-group col-md-6">
                                <label>Vpa Address</label>
                                <input type="text" class="form-control" name="account" placeholder="Enter Value">
                            </div>
                        </div>

                        <legend>Transfer Details</legend>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Amount</label>
                                <input type="number" class="form-control" name="amount" placeholder="Enter Value" required="">
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Remark (Optional)</label>
                                <input type="text" class="form-control" name="remark" placeholder="Enter Value" >
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer text-center">
                        <button class="btn bg-slate btn-raised btn-lg legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
/* Payout Card Styling */
.payout-card {
    text-decoration: none;
    color: white;
}

.card {
    border-radius: 12px;
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.15);
}

/* Icon Box */
.icon-box {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin: 0 auto;
}

/* Card Footer */
.card-footer {
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.card-footer span:hover {
    text-decoration: underline;
}
</style>
@endpush

@push('script')
<script type="text/javascript">
    var DT;
    
    $(document).ready(function () {
        $('[name="dataType"]').val("mypayout");
        var date = "{{date("Y-m-d")}}";
        var url  = "{{route('reportstatic')}}";
        var onDraw  = function() {};
        var options = [
            { "data" : "remark",
                render:function(data, type, full, meta){
                    var out = '';
                    if(full.api){
                        out +=  `<span class='myspan'>`+full.api.api_name +`</span><br>`;
                    }
                    out += `<span class='text-inverse'>`+full.id +`</span><br><span style='font-size:12px'>`+full.created_at+`</span>`;
                    return out;
                }
            },
            { "data" : "username"},
            { "data" : "username",
                render:function(data, type, full, meta){
                    if(full.option1 == "wallet"){
                        return "Wallet"
                    }else{
                        return full.number +" ( "+full.option3+" )<br>"+full.option2;
                    }
                }
            },
            { "data" : "username",
                render:function(data, type, full, meta){
                    return "RRN - "+full.refno+"<br>Payout Id - "+full.txnid;
                }
            },
            { "data" : "username",
                render:function(data, type, full, meta){
                    return `<span class='text-inverse'>Amount : <i class="fa fa-rupee"></i> `+full.amount+`<br><span class='text-inverse'>Charge : <i class="fa fa-rupee"></i> `+full.charge;
                }
            },
            { "data" : "remark"},
            { "data" : "username",
                render:function(data, type, full, meta){
                    if(full.status == "success" || full.status == "initiated"){
                        var btn = '<span class="label label-success text-uppercase"><b>'+full.status+'</b></span>';
                    }else if(full.status== 'pending'){
                        var btn = '<span class="label label-warning text-uppercase"><b>'+full.status+'</b></span>';
                    }else if(full.status == "reversed" || full.status == "refunded"){
                        var btn = `<span class="label bg-slate text-uppercase">`+full.status+`</span>`;
                    }else{
                        var btn = '<span class="label label-danger text-uppercase"><b>'+full.status+'</b></span>';
                    }
                    return btn;
                }
            }
        ];

        DT = datatableSetup(url, options, onDraw);

        $("#datatable").on('click', 'tbody td', function () {
            var data = DT.row(this).data();
            showData(data);
        });

        $( "#fundRequestForm").validate({
            rules: {
                amount: {
                    required: true
                },
                type: {
                    required: true
                },
            },
            messages: {
                amount: {
                    required: "Please enter request amount",
                },
                type: {
                    required: "Please select request type",
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
                var form = $('#fundRequestForm');

                SYSTEM.tpinVerify(function (pin) {
                    form.find('[name="pin"]').val(pin);

                    SYSTEM.FORMSUBMIT($('#fundRequestForm'), function(data){
                        form.find('[name="pin"]').val("");
                        if (!data.statusText) {
                            if(data.statuscode == "TXN"){
                                form.closest(".modal").modal('hide');
                                $.alert({
                                    icon: 'fa fa-check',
                                    theme: 'modern',
                                    animation: 'scale',
                                    type: 'green',
                                    title   : "Success",
                                    content : "Successfully Transfered, Refno - "+data.bankutr
                                });
                                $('#datatable').dataTable().api().ajax.reload();
                                getbalance();
                            }else{
                                if(
                                    data.message == "Transaction Pin is block, reset tpin" ||
                                    data.message == "Transaction Pin is incorrect" 
                                ){
                                    $.alert({
                                        title: 'Oops!',
                                        content: data.message,
                                        type: 'red'
                                    });

                                    tpinConfirm.open();
                                }else{
                                    $.alert({
                                        title: 'Oops!',
                                        content: data.message,
                                        type: 'red'
                                    });
                                }
                            }
                        } else {
                            form[0].reset();
                            form.find("[name='mode']").val('IMPS').trigger('change');
                            SYSTEM.SHOWERROR(data, form);
                        }
                    });
                });
            }
        });

        $( "#addPayoutBankForm").validate({
            rules: {
                bank: {
                    required: true
                },
                account: {
                    required: true
                },
                ifsc: {
                    required: true
                },
            },
            messages: {
                account: {
                    required: "Please enter account",
                },
                bank: {
                    required: "Please enter bank",
                },
                ifsc: {
                    required: "Please enetr ifsc",
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
                var form = $('#addPayoutBankForm');
                var inputdata = { "type": "getotp", "action" : "addbank"};

                SYSTEM.AJAX("{{route('payouttransaction')}}", "POST", inputdata, function(data){
                    if(!data.statusText){
                        if(data.status == "TXN" || data.status == "PASS"){
                            if(data.status == "TXN"){
                                SYSTEM.SYSTEM.NOTIFY('Otp send successfully', 'success');
                            }

                            if(data.status == "PASS"){
                                SYSTEM.SYSTEM.NOTIFY('Otp will be resent after 2 minutes', 'warning');
                            }

                            SYSTEM.otpVerify(function (pin) {
                                form.find('[name="otp"]').val(pin);
                                SYSTEM.FORMSUBMIT(form, function(data){
                                    form.find('[name="otp"]').val("");
                                    if (!data.statusText) {
                                        if(data.status == "TXN"){
                                            form[0].reset();
                                            form.closest(".modal").modal('hide');
                                            form.find("[name='mode']").val('IMPS').trigger('change');
                                            $.alert({
                                                icon: 'fa fa-check',
                                                theme: 'modern',
                                                animation: 'scale',
                                                type: 'green',
                                                title   : "Success",
                                                content : "Payout Bank Successfull Added"
                                            });

                                            setTimeout(function(){
                                                window.location.reload();
                                            }, 2000);
                                        }else if(data.status == "OTP"){
                                            SYSTEM.SYSTEM.NOTIFY(data.message, "error");
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
                            });

                        }else{
                            SYSTEM.SHOWERROR(data, form);
                        }
                    }else{
                        SYSTEM.SHOWERROR(data, form);
                    }
                }, form, "Sending Otp");
            }
        });
    });
</script>
@endpush