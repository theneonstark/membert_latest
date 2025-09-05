@extends('layouts.app')
@section('title', "Collection Fund Request")
@section('pagetitle',  "Collection Fund Request")

@php
    $search = "yes";
    $table = "yes";
    $export = "aeps".$action;
    $status['type'] = "Fund";
    $status['data'] = [
        "success" => "Success",
        "pending" => "Pending",
        "failed" => "Failed",
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
            <a href="javascript:void(0)">
                <div class="col-sm-3">
                    <div class="panel panel-primary invoice-grid timeline-content">
                        <div class="panel-heading">
                            <h6 class="panel-title">Transfer Limit</h6>
                        </div>

                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <ul class="list list-unstyled">
                                        <li><span>Total Limit : {{$collectionwallet}}</span></li>
                                        <li><span>Available Limit : {{$availablecollectionwallet}}</span></li>
                                        <li><span>Blocked Limit : {{$lockcollectionwallet}}</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>

            <a href="javascript:void(0)" onclick="fundRequest('collectionwallet', '')">
                <div class="col-sm-3">
                    <div class="panel border-left-lg border-left-primary invoice-grid timeline-content">
                        <div class="panel-heading">
                            <h6 class="panel-title">Move To Wallet</h6>
                        </div>

                        <div class="panel-body p-t-0">
                            <div class="row">
                                <div class="col-sm-12">
                                    <ul class="list list-unstyled">
                                        <li><span>Get settlement amount</span></li>
                                        <li><span>in your utility wallet</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer panel-footer-condensed">
                            <div class="heading-elements">
                                <span class="heading-text no-margin-left">
                                    <i class="fa fa-long-arrow-right mr-10"></i><span class="text-semibold">Click here to make request </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>

            @foreach ($banks as $bank)
                <div class="col-sm-3">
                    <div class="panel border-left-lg border-left-success invoice-grid timeline-content">
                        <div class="panel-heading">
                            <h6 class="panel-title">{{$bank->account}}</h6>
                        </div>

                        <div class="panel-body p-t-0" onclick="fundRequest('collectionbank', '{{$bank->id}}')" style="cursor: pointer;">
                            <div class="row">
                                <div class="col-sm-12">
                                    <ul class="list list-unstyled">
                                        <li>Bank : <span class="text-semibold">{{$bank->bank}}</span></li>
                                        <li>Ifsc : <span class="text-semibold">{{$bank->ifsc}}</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer panel-footer-condensed" onclick="fundRequest('collectionbank', '{{$bank->id}}')" style="cursor: pointer;">
                            <div class="heading-elements">
                                <span class="heading-text no-margin-left">
                                    <i class="fa fa-long-arrow-right mr-10"></i><span class="text-semibold">Click here to make request </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if(sizeof($banks) < 3)
{{--                 <a href="#" data-toggle="modal" data-target="#addPayoutBankModel">
                    <div class="col-sm-3">
                        <div class="panel border-left-lg border-left-danger invoice-grid timeline-content">
                            <div class="panel-heading">
                                <h6 class="panel-title">Add Payout Bank</h6>
                            </div>

                            <div class="panel-body p-t-0">
                                <div class="row">
                                    <div class="col-sm-12 text-center">
                                        <i class="fa fa-plus text-primary" style="font-size: 51px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-footer panel-footer-condensed">
                                <div class="heading-elements">
                                    <span class="heading-text no-margin-left">
                                        <i class="fa fa-long-arrow-right mr-10"></i><span class="text-semibold">Click here to Add Bank </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a> --}}
            @endif
        </div>

        <div class="row">
            <div class="col-sm-12">
                @include('layouts.filter')
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">Collection Fund Request</h4>
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
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Move To <span class="title text-capitalize"></span> Request</h6>
                </div>
                <form id="fundRequestForm" action="{{route('payouttransaction')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="type" value="collectionwallet">
                    <input type="hidden" name="gps_location">
                    <input type="hidden" name="pin">
                    <div class="modal-body">
                        <legend>Fund Settlement</legend>
                        <div class="row">
                            <div class="form-group col-md-12" style="display: none">
                                <label>Transfer Bank</label>

                                @if(sizeof($banks) < 3)
                                    {{-- <button type="button" class="btn btn-xs btn-primary pull-right" data-toggle="modal" data-target="#addPayoutBankModel">Add New Bank</button> --}}
                                @endif

                                <select name="accountType" class="form-control select">
                                    <option value="">Select Tranfer Bank</option>
                                    @foreach ($banks as $bank)
                                        <option value="{{$bank->id}}">{{$bank->account}} ({{$bank->bank}})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-12">
                                <label>Amount</label>
                                <input type="number" class="form-control" name="amount" placeholder="Enter Value" required="">
                            </div>

                            <div class="form-group col-md-12">
                                <label>Transfer Mode</label>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" class="styled" name="mode" value="IMPS">
                                        <span class="text-danger">IMPS Transfer (It will be chargeable)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                        <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                    </div>
                </form>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div>

    <div id="addPayoutBankModel" class="modal fade right" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Add Payout Bank</h6>
                </div>

                <form id="addPayoutBankForm" action="{{route('payouttransaction')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="type" value="addbank">
                    <input type="hidden" name="gps_location">
                    <input type="hidden" name="otp">

                    <div class="modal-body">
                        <legend>Bank Details</legend>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Account Holder Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Value">
                            </div>

                            <div class="form-group col-md-6">
                                <label>Account Number</label>
                                <input type="text" class="form-control" name="account" placeholder="Enter Value">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Bank Name</label>
                                <input type="text" class="form-control" name="bank" placeholder="Enter Value">
                            </div>

                            <div class="form-group col-md-6">
                                <label>Ifsc Code</label>
                                <input type="text" class="form-control" name="ifsc" placeholder="Enter Value">
                            </div>
                        </div>

                        <p>Note : Account holder name should be matched with your registred name or firm name</p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                        <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Add Bank</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('style')

@endpush

@push('script')
<script type="text/javascript">
    var DT;
    $(document).ready(function () {
        var date = "{{date("Y-m-d")}}";
        var url = "{{url('fund/other/statics')}}/collection{{$action}}";
        var onDraw = function() {};
        var options = [
            { "data" : "username",
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
            { "data": "username",
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
                            if(data.status == "TXN"){
                                form.closest(".modal").modal('hide');
                                $.alert({
                                    icon: 'fa fa-check',
                                    theme: 'modern',
                                    animation: 'scale',
                                    type: 'green',
                                    title   : "Success",
                                    content : "Successfully Transfered, Refno - "+data.txnid
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
                            form.find("[name='accountType']").val('').trigger('change');
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

    function fundRequest(type, accountType) {
        $(".title").text(type);
        $("#fundRequestForm").find("[name='type']").val(type);
        $('[name="accountType"]').select2().val(accountType).trigger('change');
        if(type == "collectionbank"){
            $('[name="mode"]').closest('.form-group').show();
            $('[name="accountType"]').closest('.form-group').show();
        }else{
            $('[name="mode"]').closest('.form-group').hide();
            $('[name="accountType"]').closest('.form-group').hide();
        }

        $("#fundRequestModal").modal();
    }
</script>
@endpush