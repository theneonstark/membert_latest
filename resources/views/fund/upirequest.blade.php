@extends('layouts.app')
@section('title', "Scan & Load Money")
@section('pagetitle',  "Scan & Load Money")

@php
    $table = "yes";

    $status['type'] = "Fund";
    $status['data'] = [
        "success" => "Success",
        "pending" => "Pending",
        "failed" => "Failed",
        "approved" => "Approved",
        "rejected" => "Rejected",
    ];


    if(!Auth::user()->qrcode){
        $search = "hide";
    }
@endphp

@section('content')
    <div class="content">
        @if(!Auth::user()->qrcode)
            <div class="row">
    <div class="col-sm-12">
        <div class="panel">
            <div class="panel-heading">Create VPA</div>
            <form action="{{route('fundtransaction')}}" method="post" id="fingkycForm">
                <div class="panel-body"> 
                    {{ csrf_field() }}
                    <input type="hidden" name="type" value="qrcode">
                    
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>Name</label>
                            <input type="text" class="form-control" name="merchant_name" placeholder="Enter Name" value="{{Auth::user()->name}}" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Mobile</label>
                            <input type="text" pattern="[0-9]*" maxlength="10" minlength="10" class="form-control" name="mobile" placeholder="Enter Your Mobile" value="{{Auth::user()->mobile}}" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Pancard</label>
                            <input type="text" class="form-control" name="pan" placeholder="Enter Pancard" value="{{Auth::user()->pancard}}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-12">
                            <label>Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Enter Address" value="{{Auth::user()->address}}" required>
                        </div>   
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>City</label>
                            <input type="text" class="form-control" name="city" value="{{Auth::user()->city}}" placeholder="Enter Your City" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label>State</label>
                            <input type="text" class="form-control" name="state" value="{{Auth::user()->state}}" placeholder="Enter State" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Pin Code</label>
                            <input type="text" class="form-control" name="pincode" maxlength="6" minlength="6" pattern="[0-9]*" value="{{Auth::user()->pincode}}" placeholder="Enter Pincode" required>
                        </div>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" class="btn btn-custom">
                        <i class="icon-paperplane"></i> Proceed To Onboard
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
        @else
            <div class="row">
                @if(Auth::user()->qrcode)
                    <div class="col-sm-4">
                        <div class="panel border-left-lg border-left-success invoice-grid timeline-content">
                            <div class="panel-body text-center">
                                <div class="qrimagestatic"></div>
                                <h4 class="pn">{{Auth::user()->merchant_name}}</h4>
                                <h5 class="vpa">{{Auth::user()->vpa}}</h5>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Scan & Load Money</h4>
                            <div class="heading-elements">
                                <button type="button" data-toggle="modal" data-target="#fundRequestModal" class="btn bg-slate btn-xs btn-labeled legitRipple btn-lg" data-loading-text="<b><i class='fa fa-spin fa-spinner'></i></b> Searching"><b><i class="icon-plus2"></i></b> Dynamic Qr Code</button>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped table-hover" id="datatable">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Order ID</th>
                                    <th>Transaction Details</th>
                                    <th>Refrence Details</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div id="fundRequestModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Scan & Pay</h6>
                </div>
                <form id="fundRequestForm" action="{{route('fundtransaction')}}" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="user_id">
                        <input type="hidden" name="type" value="qrcode_dynamic">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Amount</label>
                                <input type="number" name="amount" step="any" class="form-control" placeholder="Enter Amount" required="">
                            </div>
                            
                            <div class="qrimage"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                        <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('style')

<style>
    .panel {
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease-in-out;
        background: #ffffff;
        overflow: hidden;
    }

    .panel:hover {
        transform: translateY(-5px);
    }

    .panel-heading {
        background: #008080;
        color: #ffffff;
        padding: 15px;
        font-size: 18px;
        font-weight: bold;
        border-radius: 12px 12px 0 0;
        text-align: center;
    }

    .panel-body {
        padding: 20px;
    }

    .panel-footer {
        padding: 15px;
        text-align: center;
        background: #f8f9fa;
        border-radius: 0 0 12px 12px;
    }

    .form-group label {
        font-weight: 600;
        color: #333;
    }

    .form-control {
        border-radius: 8px;
        height: 45px;
        font-size: 16px;
        border: 1px solid #ccc;
        padding: 10px;
    }

    .btn-custom {
        width: 100%;
        padding: 12px;
        font-size: 18px;
        font-weight: bold;
        border-radius: 8px;
        border: none;
        transition: background 0.3s ease;
        background: #008080;
        color: white;
    }

    .btn-custom:hover {
        background: #006666;
    }

    @media (max-width: 768px) {
        .col-md-4, .col-md-12 {
            width: 100%;
        }
    }
</style>

@endpush

@push('script')
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.qrcode/1.0/jquery.qrcode.min.js"></script> 
    <script type="text/javascript" src="{{asset('')}}assets/js/core/sweetalert2.all.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function () {
            $(window).load(function(){
                jQuery(".qrimagestatic").qrcode({
                    width  : 250,
                    height : 250,
                    text: "{{Auth::user()->qrcode ?? ''}}"
                });
            });

            $('[name="dataType"]').val("gatewayload");
            var url = "{{route("reportstatic")}}";

            var onDraw = function() {};
            var options = [
                { "data" : "status",
                    render:function(data, type, full, meta){
                        if(full.status == "success"){
                            var out = `<span class="label label-success">Success</span>`;
                        }else if(full.status == "pending"){
                            var out = `<span class="label label-warning">Pending</span>`;
                        }else if(full.status == "reversed" || full.status == "refunded"){
                            var out = `<span class="label bg-slate">`+full.status+`</span>`;
                        }else{
                            var out = `<span class="label label-danger">`+full.status+`</span>`;
                        }
                        return out;
                    }
                },
                { "data" : "name",
                    render:function(data, type, full, meta){
                        return `<div>
                                <span class=''>`+full.apiname+ ` (`+full.via+`)` +`</span><br>
                                <span class='text-inverse m-l-10'>SN : <b>`+full.id +`</b> </span>
                                <div class="clearfix"></div>
                            </div><span style='font-size:13px' class="pull=right">`+full.created_at+`</span>`;
                    }
                },
                { "data" : "bank",
                    render:function(data, type, full, meta){
                        return "Number : "+full.number+"<br>Provider: "+full.providername;
                    }
                },
                { "data" : "bank",
                    render:function(data, type, full, meta){
                        return "Reference / Utr: "+full.refno+"<br>Txn Id : "+full.txnid+"<br>Pay Id : "+full.payid;
                    }
                },
                { "data" : "bank",
                    render:function(data, type, full, meta){
                        return "Amount : "+full.amount;
                    }
                }
            ];

            datatableSetup(url, options, onDraw);

            $( "#fundRequestForm").validate({
                rules: {
                    amount: {
                        required: true
                    }
                },
                messages: {
                    amount: {
                        required: "Please enter request amount",
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
                    form.ajaxSubmit({
                        dataType:'json',
                        beforeSubmit:function(){
                            form.find('button:submit').button('loading');
                        },
                        complete: function () {
                            form.find('button:submit').button('reset');
                        },
                        success:function(data){
                            if(data.statuscode == "TXN"){
                                jQuery(".qrimage").qrcode({
                                    width  : 250,
                                    height : 250,
                                    text: data.qr_lLink
                                });
                            }else{
                                notify(data.message , 'warning');
                            }
                        },
                        error: function(errors) {
                            showError(errors, form);
                        }
                    });
                }
            });

            $( "#fingkycForm").validate({
                rules: {
                    merchant_name: {
                        required: true
                    }
                },
                messages: {
                    merchant_name: {
                        required: "Please enter value",
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
                    var form = $('#fingkycForm');
                    form.ajaxSubmit({
                        dataType:'json',
                        beforeSubmit:function(){
                            form.find('button:submit').button('loading');
                        },
                        complete: function () {
                            form.find('button:submit').button('reset');
                        },
                        success:function(data){
                            if(data.statuscode == "TXN"){
                                form.closest('.modal').modal('hide');
                                window.location.reload();
                            }else{
                                notify(data.message , 'warning');
                            }
                        },
                        error: function(errors) {
                            showError(errors, form);
                        }
                    });
                }
            });
        });

        function fundRequest(id = "none"){
            if(id != "none"){
                $('#fundRequestForm').find('[name="fundbank_id"]').select2().val(id).trigger('change');
            }
            $('#fundRequestModal').modal();
        }
    </script>
@endpush