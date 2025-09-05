@extends('layouts.app')
@section('title', "Transaction History")
@section('pagetitle',  "Transaction")

@php
    $table     = "yes";
    $newexport = "yes";
    $status['type'] = "Report";
    $status['data'] = [
        "initiated" => "Initiated",
        "success"   => "Success",
        "pending"   => "Pending",
        "failed"    => "Failed",
        "reversed"  => "Reversed",
        "refunded"  => "Refunded"
    ];

    $billers = App\Models\Api::whereIn('type', ['money', 'recharge', 'bill', 'pancard'])->orderBy('name', 'asc')->get(['id', 'name']);
    foreach ($billers as $item){
        $product['data'][$item->id] = $item->name;
    }
    $product['type'] = "Product";

    if($id != 0){
        $agentfilter = "hide";
    }
@endphp

@section('content')
<div class="content p-b-0">
    <div class="tabbable">
        <ul class="nav nav-tabs bg-material nav-tabs-component custom-tabs">
            <li><a href="#recharge" data-toggle="tab" id="payinTab" class="legitRipple" onclick="SETTITLE('payin')">Pay-In</a></li>
            <li><a href="#recharge" data-toggle="tab" id="payoutTab" class="legitRipple" onclick="SETTITLE('payout')">Pay-Out</a></li>
            <li><a href="#recharge" data-toggle="tab" id="qrcodeTab" class="legitRipple" onclick="SETTITLE('qrcode')">Qr-Code</a></li>
            <li><a href="#recharge" data-toggle="tab" id="chargebackTab" class="legitRipple" onclick="SETTITLE('chargeback')">Charge-Back</a></li>
        </ul>
    </div>
</div>

    <div class="content p-b-0">
        @include('layouts.filter')
        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default table">
                    <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                        <h4 class="panel-title"><span class="titleName"></span> Statement</h4>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th width="120px">Date</th>
                                <th>Status</th>
                                @if(Myhelper::hasRole("whitelable"))
                                <th>User Details</th>
                                @endif
                                <th>Operator</th>
                                <th>Number</th>
                                <th>Api Txnid</th>
                                <th>Txnid</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Charge</th>
                                <th>Gst</th>
                                <th>Profit</th>
                                <th>Tds</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
    .custom-tabs {
        display: flex;
        justify-content: space-around;
        background: linear-gradient(45deg, #00887b, #00807f);
        border-radius: 10px;
        padding: 10px;
    }
    
    .custom-tabs li {
        list-style: none;
    }
    
    .custom-tabs a {
        display: block;
        padding: 10px 20px;
        color: white;
        font-weight: bold;
        border-radius: 5px;
        transition: background 0.3s ease, transform 0.2s;
        border-radius: 10px !important;
        overflow: hidden;
    }
    
    .custom-tabs a:hover, 
    .custom-tabs a:focus {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }
    
    .custom-tabs  .active > a, .active > a:after {
        background: white !important;
        color: #007bff !important;
    }

    @media (max-width: 600px) {
        .custom-tabs {
            flex-direction: column;
            text-align: center;
        }
        .custom-tabs a {
            margin-bottom: 5px;
        }
    }
</style>
@endpush

@push('script')
<script src="{{ asset('/assets/js/core/jQuery.print.js') }}"></script>
<script type="text/javascript">
    var transtype = "", myurl = "{{route('reportstatic')}}";
    var DT, onDraw, options;

    $(document).ready(function () {
        $(window).load(function () {
            $("#aepsTab").trigger("click");
        });

        @if(isset($id) && $id != 0)
            $('form#searchForm').find('[name="agent"]').val("{{$id}}");
        @endif

        $('#print').click(function(){
            $('#receptTable').print();
        });

        $("#datatable").on('click', 'tbody td', function () {
            if(!$(this).hasClass("notClick")){
                var data = DT.row(this).data();
                showData(data);
            }
        });
        
        onDraw = function() {
            // $('.print').click(function(event) {
            //     var data = DT.row($(this).parent().parent().parent().parent().parent()).data();
            //     $.each(data, function(index, values) {
            //         $("."+index).text(values);
            //     });

            //     if(data['product'] == "dmt"){
            //         $('address.dmt').show();
            //         $('address.notdmt').hide();
            //     }else{
            //         $('address.notdmt').show();
            //         $('address.dmt').hide();
            //     }
            //     $('#receipt').modal();
            // });
        };

        options = [
            { "data" : "created_at",
                render: function(data, type, full, meta){
                    return moment(full.created_at, "YYYY-MM-DD HH:mm:ss").format("DD MMM YYYY HH:mm")
                }
            },
            { "data" : "status",
                "className" : "notClick",
                render: function(data, type, full, meta){
                    if(full.status == "success"){
                        var out = `<span class="label label-success">Success</span>`;
                    }else if(full.status == "pending"){
                        var out = `<span class="label label-warning">Pending</span>`;
                    }else if(full.status == "reversed" || full.status == "refunded"){
                        var out = `<span class="label bg-slate">`+full.status+`</span>`;
                    }else{
                        var out = `<span class="label label-danger">`+full.status+`</span>`;
                    }

                    if(full.product == "qrcode" && (full.status == "pending" || full.status == "failed")){
                        out += `<button type="button" class="btn bg-success ms-5 btn-raised legitRipple btn-xs" onclick="complaint('`+full.id+`')"> Raise Complaint</button>`;
                    }

                    return out;
                }
            },
            @if(Myhelper::hasRole("whitelable"))
                { "data" : "created_at",
                    render: function(data, type, full, meta){
                        return full.username + " ("+full.user_id+")";
                    }
                },
            @endif

            { "data" : "providername"},
            { "data" : "number"},
            { "data" : "apitxnid"},
            { "data" : "txnid"},
            { "data" : "refno"},
            { "data" : "amount",
                render:function(data, type, full, meta){
                    if(full.product == "qrcode"){
                        return full.option1;  
                    }else{
                        return full.amount;  
                    }
                }
            },
            { "data" : "charge",
                render:function(data, type, full, meta){
                    if(full.product == "qrcode"){
                        return full.amount;  
                    }else{
                        return full.charge;
                    }
                }
            },
            { "data" : "gst"},
            { "data" : "profit"},
            { "data" : "tds"},
        ];

        $( "#otpForm" ).validate({
            rules: {
                otp: {
                    required: true,
                    number : true,
                },
            },
            messages: {
                otp: {
                    required: "Please enter otp number",
                    number: "Otp number should be numeric",
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
                var form = $('#otpForm');
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button[type="submit"]').button('loading');
                    },
                    success:function(data){
                        form.find('button[type="submit"]').button('reset');
                        if(data.statuscode == "TXN"){
                            form[0].reset();
                            $('#otpModal').find('[name="transid"]').val("");
                            $('#otpModal').modal('hide');
                            SYSTEM.NOTIFY('Transaction Successfully Refunded, Amount Credited', 'success');
                        }else{
                            SYSTEM.NOTIFY(data.message, 'danger', "inline",form);
                        }
                    },
                    error: function(errors) {
                        showError(errors, form);
                    }
                });
            }
        });
    });

    function SETTITLE(type) {
        $('[name="dataType"]').val(type);
        $(".titleName").text(type.toUpperCase());
        if(transtype == ""){
            DT = datatableSetup(myurl, options, onDraw);
        }else{
            $('#datatable').dataTable().api().ajax.url(myurl).load(null, false);
        }
        transtype = type;
    }

    function viewUtiid(id){
        $.ajax({
            url: `{{url('statement/list/fetch')}}/utiidstatement/`+id,
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            data:{'scheme_id':id}
        })
        .done(function(data) {
            $.each(data, function(index, values) {
                $("."+index).text(values);
            });
            $('#utiidModal').modal();
        })
        .fail(function(errors) {
            SYSTEM.NOTIFY('Oops', errors.status+'! '+errors.statusText, 'warning');
        });
    }
</script>
@endpush