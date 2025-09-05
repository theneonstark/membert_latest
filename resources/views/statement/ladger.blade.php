
@extends('layouts.app')
@section('title', "Account Ladger")
@section('pagetitle',  "Ladger")

@php
    $table  = "yes";
    $newexport = "yes";
@endphp

@section('content')
    <div class="content p-b-0" >
        <div class="tabbable">
            <ul class="nav nav-tabs bg-material nav-tabs-component" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                <li>
                    <a href="#recharge" data-toggle="tab" id="aepsTab" class="legitRipple" aria-expanded="false" onclick="SETTITLE('collectionwallet')">Collection Wallet</a>
                </li>

                <li>
                    <a href="#recharge" data-toggle="tab" id="cashdepositTab" class="legitRipple" aria-expanded="false" onclick="SETTITLE('mainwallet')">Payout Wallet</a>
                </li>
                
                <li>
                    <a href="#recharge" data-toggle="tab" id="cashdepositTab" class="legitRipple" aria-expanded="false" onclick="SETTITLE('qrwallet')">Qr Wallet</a>
                </li>

                <li>
                    <a href="#recharge" data-toggle="tab" id="cashdepositTab" class="legitRipple" aria-expanded="false" onclick="SETTITLE('rrwallet')">Rr Wallet</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content p-b-0">
        @include('layouts.filter')
        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default">
                    <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                        <h4 class="panel-title text-capitalize"><span class="titleName"></span> Statement</h4>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th width="200px">#</th>
                                <th>Product</th>
                                <th>Txnid</th>
                                <th>Amount</th>
                                <th>Charge</th>
                                <th>Gst</th>
                                <th>Opening Bal.</th>
                                <th>CR/DR</th>
                                <th>Closing Bal.</th>
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

@push('script')
<script src="{{ asset('/assets/js/core/jQuery.print.js') }}"></script>
<script type="text/javascript">
    var DT;

    $(document).ready(function () {
        $(window).load(function (){
            $("#aepsTab").trigger("click");
        });

        @if(isset($id) && $id != 0)
            $('form#searchForm').find('[name="agent"]').val("{{$id}}");
        @endif

        $('#print').click(function(){
            $('#receptTable').print();
        });
        
        var url = "{{route('reportstatic')}}";
        var onDraw = function() {
            $('.print').click(function(event) {
                var data = DT.row($(this).parent().parent().parent().parent().parent()).data();
                $.each(data, function(index, values) {
                    $("."+index).text(values);
                });

                if(data['product'] == "dmt"){
                    $('address.dmt').show();
                    $('address.notdmt').hide();
                }else{
                    $('address.notdmt').show();
                    $('address.dmt').hide();
                }
                $('#receipt').modal();
            });
        };

        var options = [
            { "data" : "created_at",
                render: function(data, type, full, meta){
                    return moment(full.created_at, "YYYY-MM-DD HH:mm:ss").format("DD MMM YYYY HH:mm")
                }
            },
            { "data" : "product"},
            { "data" : "txnid"},
            { "data" : "amount"},
            { "data" : "charge"},
            { "data" : "gst"},
            { "data" : "balance"},
            { "data" : "created_at",
                render:function(data, type, full, meta){
                    var amount = 0;
                    if(full.trans_type == "credit"){
                        amount = full.closing - full.balance;
                    }else{
                        amount = full.balance - full.closing;   
                    }
                    
                    return amount.toFixed(2)+" / "+full.trans_type;
                }
            },
            { "data" : "closing"}
        ];

        DT = datatableSetup(url, options, onDraw);

        $("#datatable").on('click', 'tbody td', function () {
            var data = DT.row(this).data();
            showData(data);
        });
    });

    function SETTITLE(type) {
        $('[name="dataType"]').val(type);
        $(".titleName").text(type);
        $('#datatable').dataTable().api().ajax.reload(null, false);
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