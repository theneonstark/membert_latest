@extends('layouts.app')
@section('title', "Scan & Pay")
@section('pagetitle', "Scan & Pay")
@php
    $table = "yes";
@endphp

@section('content')
    <div class="content">

<div class="row">
    <!-- Scan & Pay -->
    <div class="col-sm-6">
        <div class="panel">
            <div class="panel-heading">Scan & Pay</div>
            <form id="qrForm" action="{{route('fundtransaction')}}" method="post">
                {{ csrf_field() }}
                <input type="hidden" name="transactionType" value="addmoney">
                <input type="hidden" name="gps_location">
                <div class="panel-body">
                    <div class="form-group">
                        <label>Load Amount</label>
                        <input type="text" name="amount" class="form-control" placeholder="Enter amount" required="">
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-custom text-white" style="background: #05aea4;">
                        <i class="icon-paperplane"></i> Pay Now
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Move to Payout Wallet -->
    <div class="col-sm-6">
        <div class="panel">
            <div class="panel-heading">Settlement Request</div>
            <form id="settlementForm" action="{{route('fundtransaction')}}" method="post">
                {{ csrf_field() }}
                <input type="hidden" name="transactionType" value="settlementRequest">
                <div class="panel-body">
                    <div class="form-group">
                        <label>Request Amount</label>
                        <input type="text" name="amount" class="form-control" placeholder="Enter amount" required="">
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-custom text-white" style="background: #05aea4;">
                        <i class="icon-paperplane"></i> Pay Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


        @include('layouts.filter')

        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
    					<h4 class="panel-title">Instant Load Request</h4>
    				</div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Txn Id</th>
                                <th>Ref Id</th>
                                <th>Bank Ref</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="qrcodeModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Scan & Pay</h6>
                </div>
                <div class="modal-body text-center">
                    <h3>{{ucfirst(\Auth::user()->companyname)}} Scan & Pay</h3>
                    <div class="qrimage"></div>
                    <h4 class="pn"></h4>
                    <h5 class="vpa"></h5>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                </div>
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
        transform: translateY(-2px);
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
    }

    .btn-custom:hover {
        background: #006666;
    }

    @media (max-width: 768px) {
        .col-sm-4 {
            width: 100%;
            margin-bottom: 20px;
        }
    }
    
    /*me end*/
         .swal2-footer{
            font-size: 14px !important;
        }

        .swal2-popup{
            min-width: 42em !important;
        }

        .swal2-title{
            font-size: 24px !important;
        }

        .swal2-title{
            font-size: 24px !important;
        }

        .swal2-html-container{
            font-size: 20px !important;
        }

        .swal2-loader {
            width: 3.2em !important;
            height: 3.2em !important;
            border-width: 0.3em !important;
        }

        .swal2-timer-progress-bar-container {
            height: 0.5em !important;
        }

        .swal2-timer-progress-bar {
            height: 0.5em !important;
            background: rgb(255 34 34) !important;
        }

        .swal2-popup .swal2-title {
            margin: 20px 0 0.4em !important;
        }
    </style>
@endpush

@push('script')
<script type="text/javascript" src="{{asset('')}}assets/js/core/sweetalert2.all.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.qrcode/1.0/jquery.qrcode.min.js"></script> 
	<script type="text/javascript">
    $(document).ready(function () {
        var url = "{{url('statement/list/fetch')}}/upistatement/0";

        var onDraw = function() {};

        var options = [
            { "data" : "created_at"},
            { "data" : "merchantTranId"},
            { "data" : "refId"},
            { "data" : "rrn"},
            { "data" : "amount"},
            { "data" : "status",
                render:function(data, type, full, meta){
                    if(full.status == "success"){
                        var out = `<span class="label label-success">Success</span>`;
                    }else if(full.status == "initiated"){
                        var out = `<span class="label label-info">Initiated</span>`;
                    }else if(full.status == "pending"){
                        var out = `<span class="label label-warning">Pending</span>`;
                    }else if(full.status == "reversed"){
                        var out = `<span class="label bg-slate">Reversed</span>`;
                    }else{
                        var out = `<span class="label label-danger">Failed</span>`;
                    }
                    return out;
                }
            }
        ];

        datatableSetup(url, options, onDraw);

        $( "#qrForm" ).validate({
            rules: {
                amount: {
                    required: true,
                    number : true,
                    min: 1
                },
            },
            messages: {
                amount: {
                    required: "Please enter  amount",
                    number: "Amount should be numeric",
                    min: "Min  amount value rs 10",
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
                var form = $('#qrForm');
                var id = form.find('[name="id"]').val();
                var type = form.find('[name="transactionType"]').val();
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));

                SYSTEM.FORMSUBMIT(form, function(data){
                    form.find('[name="otp"]').val("");
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            form[0].reset();
                            Swal.fire({
                                title: 'Scan & Pay',
                                html: `<div class="qrimage"></div>
                                        <h4 class="pn"></h4>
                                        <h5 class="vpa"></h5>`,
                                footer: 'Please do not hit back button untill complete payment',
                                timer: 60000,
                                timerProgressBar: true,
                                didOpen: () => {
                                    Swal.showLoading()
                                    jQuery(".qrimage").qrcode({
                                        width  : 250,
                                        height : 250,
                                        text: data.qr_link
                                    });
                                    timerInterval = setInterval(() => {
                                        checkStatus(data.merchantTranId);
                                    }, 5000);
                                },
                                willClose: () => {
                                    clearInterval(timerInterval)
                                },
                                showConfirmButton: false,
                                allowOutsideClick: () => false
                            });
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
        });

        $( "#urlForm" ).validate({
            rules: {
                amount: {
                    required: true,
                    number : true,
                    min: 1
                },
            },
            messages: {
                amount: {
                    required: "Please enter  amount",
                    number: "Amount should be numeric",
                    min: "Min  amount value rs 10",
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
                var form = $('#urlForm');
                var id = form.find('[name="id"]').val();
                var type = form.find('[name="transactionType"]').val();
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));

                SYSTEM.FORMSUBMIT(form, function(data){
                    form.find('[name="otp"]').val("");
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            form[0].reset();
                            Swal.fire({
                                title: 'Qr Code',
                                html:  `<p class="payment_link"></p>`,
                                footer: 'Please do not hit back button untill complete payment',
                                timer: 60000,
                                timerProgressBar: true,
                                didOpen: () => {
                                    Swal.showLoading()
                                    $(".payment_link").text(data.payment_link)
                                    timerInterval = setInterval(() => {
                                        checkStatus(data.merchantTranId);
                                    }, 5000);
                                },
                                willClose: () => {
                                    clearInterval(timerInterval)
                                },
                                showConfirmButton: false,
                                allowOutsideClick: () => false
                            });
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
        });

        $( "#settlementForm" ).validate({
            rules: {
                amount: {
                    required: true,
                    number : true,
                    min: 1
                },
            },
            messages: {
                amount: {
                    required: "Please enter  amount",
                    number: "Amount should be numeric",
                    min: "Min  amount value rs 10",
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
                var form = $('#settlementForm');
                var id = form.find('[name="id"]').val();
                var type = form.find('[name="transactionType"]').val();
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));

                SYSTEM.FORMSUBMIT(form, function(data){
                    form.find('[name="otp"]').val("");
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            form[0].reset();
                            $.alert({
                                title: 'Success!',
                                content: data.message,
                                type: 'green'
                            });
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
        });

        $( "#fundForm" ).validate({
            rules: {
                amount: {
                    required: true,
                    number : true,
                    min: 1
                },
            },
            messages: {
                amount: {
                    required: "Please enter  amount",
                    number: "Amount should be numeric",
                    min: "Min  amount value rs 10",
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
                var form = $('#fundForm');
                var id = form.find('[name="id"]').val();
                var type = form.find('[name="transactionType"]').val();
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));

                SYSTEM.FORMSUBMIT(form, function(data){
                    form.find('[name="otp"]').val("");
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            form[0].reset();
                            $.alert({
                                title: 'Success!',
                                content: data.message,
                                type: 'green'
                            });
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
        });

        $( "#collectForm" ).validate({
            rules: {
                amount: {
                    required: true,
                    number : true,
                    min: 1
                },
            },
            messages: {
                amount: {
                    required: "Please enter  amount",
                    number: "Amount should be numeric",
                    min: "Min  amount value rs 10",
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
                var form = $('#collectForm');
                var id = form.find('[name="id"]').val();
                var type = form.find('[name="transactionType"]').val();
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button[type="submit"]').button('loading');
                    },
                    success:function(data){
                        form.find('button[type="submit"]').button('reset');
                        if(data.status == "TXN"){
                            form[0].reset();
                            Swal.fire({
                                title: 'Collect Request',
                                imageUrl:  '{{asset('')}}assets/upi.png',
                                imageWidth: 150,
                                text : 'Open your upi app and complete the payment',
                                footer: 'Please do not hit back button untill complete payment',
                                timerProgressBar: true,
                                timer: 300000,
                                didOpen: () => {
                                    Swal.showLoading()
                                    timerInterval = setInterval(() => {
                                        checkStatus(data.txnid);
                                    }, 5000);
                                },
                                willClose: () => {
                                    clearInterval(timerInterval)
                                },
                                showConfirmButton: false,
                                allowOutsideClick: () => false
                            });
                        }else{
                            SYSTEM.NOTIFY("Recharge "+data.status+ "! "+data.description, 'warning');
                        }
                    },
                    error: function(errors) {
                        showError(errors, form);
                    }
                });
            }
        });
    });

    function checkStatus(txnid) {
        $.ajax({
            url: "{{route('fundtransaction')}}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            data: {'transactionType' : "upistatus" , "txnid" : txnid},
            success: function(data){
                if(data.status == "TXN"){
                    clearInterval(timerInterval);
                    Swal.fire(
                      'Success',
                      'Payment Received Successfull, Your wallet is credited successfully',
                      'success'
                    );
                }else if(data.status == "TXF"){
                    clearInterval(timerInterval);
                    Swal.fire(
                      'Failed',
                      'Transaction Failed, try again',
                      'error'
                    );
                }
            }
        });
    }
</script>
@endpush