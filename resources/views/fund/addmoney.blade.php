@extends('layouts.app')
@section('title', "Wallet Load Request")
@section('pagetitle',  "Wallet Load Request")

@php
    $table = "yes";
    $export = "fund".$type;
    $search = "yes";
    $status['type'] = "Fund";
    $status['data'] = [
        "success" => "Success",
        "pending" => "Pending",
        "failed" => "Failed",
        "approved" => "Approved",
        "rejected" => "Rejected",
    ];
@endphp

@section('content')
    <div class="content">
        <div class="row">
            @if ($banks)
                @foreach ($banks as $bank)
                <div class="col-md-4">
    <div class="card border-start border-3 border-success shadow-sm hover-effect" style="background: linear-gradient(135deg, #01b3a2, #038584); padding: 20px; margin-bottom: 20px;">
        <div class="card-body text-white">
            <div class="row">
                <!-- Left Section (Bank Name & IFSC) -->
                <div class="text-end col-md-6">
                    <h6 class="fw-bold mb-1">{{ $bank->name }} <span>: {{ $bank->account }}</span></h6>
                    <p class="mb-0 ">IFSC : <span class="fw-semibold">{{ $bank->ifsc }}</span></p>
                    <h6 class="fw-bold  mb-1"></h6>
                    <p class="mb-0">Branch : <span class="fw-semibold">{{ $bank->branch }}</span></p>
                </div>

                <!-- Right Section (Account Number & Branch) -->
                <div class="text-end col-md-6">
                </div>
            </div>
        </div>

        <!-- Card Footer as Clickable Action -->
        <div class="card-footer bg-light text-center py-2 w-100">
            <a href="javascript:void(0)" onclick="fundRequest({{ $bank->id }})" class="btn btn-success btn-sm">
                <i class="fa fa-paper-plane me-2"></i> Request Payout
            </a>
        </div>
    </div>
</div>
                @endforeach
            @endif
        </div>

        @include('layouts.filter')
        
        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default" style="border-radius: 10px;">
                    <div class="panel-heading" style="border-radius: 10px; background:  #038584;">
                        <h4 class="panel-title">Wallet Load Request</h4>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Deposit Bank Details</th>
                                <th>Refrence Details</th>
                                <th>Amount</th>
                                <th>Remark</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="fundRequestModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Wallet Fund Request</h6>
                </div>
                <form id="fundRequestForm" action="{{route('fundtransaction')}}" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="user_id">
                        <input type="hidden" name="transactionType" value="request">
                        <input type="hidden" name="gps_location">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Deposit Bank</label>
                                <select name="fundbank_id" class="form-control select" id="select" required>
                                    <option value="">Select Bank</option>
                                    @foreach ($banks as $bank)
                                    <option value="{{$bank->id}}">{{$bank->name}} ( {{$bank->account}} )</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Amount</label>
                                <input type="number" name="amount" step="any" class="form-control" placeholder="Enter Amount" required="">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Payment Mode</label>
                                <select name="paymode" class="form-control select" id="select" required>
                                    <option value="">Select Paymode</option>
                                    @foreach ($paymodes as $paymode)
                                    <option value="{{$paymode->name}}">{{$paymode->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Pay Date</label>
                                <input type="text" name="paydate" class="form-control mydate" placeholder="Select date">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Ref No.</label>
                                <input type="text" name="ref_no" class="form-control" placeholder="Enter Reference Number" required="">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Pay Slip (Optional)</label>
                                <input type="file" name="payslips" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Remark</label>
                                <textarea name="remark" class="form-control" rows="2" placeholder="Enter Remark"></textarea>
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
@endsection

@push('style')
<style>
/* Hover Effect */
.hover-effect {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-radius: 10px;
}
.hover-effect:hover {
    transform: translateY(-5px);
    box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.15);
}

/* Improved Spacing */
.card-footer a {
    transition: background 0.2s ease-in-out;
}
.card-footer a:hover {
    background: #28a745 !important;
    color: white !important;
}
</style>
@endpush

@push('script')
<script type="text/javascript">
    $(document).ready(function () {
        var url = "{{url('fund/utility/statics')}}/fund{{$type}}";
        var onDraw = function() {};
        var options = [
            { "data" : "id",
                render:function(data, type, full, meta){
                    return `<span class='text-inverse m-l-10'><b>`+full.id +`</b> </span><br>
                        <span style='font-size:13px'>`+full.updated_at+`</span>`;
                }
            },
            { "data" : "id",
                render:function(data, type, full, meta){
                    return `Name - `+full.bankname+`<br>Account No. - `+full.bankaccount+`<br>Branch - `+full.bankbranch;
                }
            },
            { "data" : "id",
                render:function(data, type, full, meta){
                    var slip = '';
                    if(full.payslip){
                        var slip = `<a target="_blank" href="{{asset('public')}}/deposit_slip/`+full.payslip+`">Pay Slip</a>`
                    }
                    return `Ref No. - `+full.ref_no+`<br>Paydate - `+full.paydate+`<br>Paymode - `+full.paymode+` ( `+slip+` )`;
                }
            },
            { "data" : "amount"},
            { "data" : "remark"},
            { "data" : "id",
                render:function(data, type, full, meta){
                    var out = '';
                    if(full.status == "approved"){
                        out += `<label class="label label-success">Approved</label>`;
                    }else if(full.status == "pending"){
                        out += `<label class="label label-warning">Pending</label>`;
                    }else if(full.status == "rejected"){
                        out += `<label class="label label-danger">Rejected</label>`;
                    }

                    return out;
                }
            }
        ];

        datatableSetup(url, options, onDraw);

        $( "#fundRequestForm").validate({
            rules: {
                fundbank_id: {
                    required: true
                },
                amount: {
                    required: true
                },
                paymode: {
                    required: true
                },
                ref_no: {
                    required: true
                },
            },
            messages: {
                fundbank_id: {
                    required: "Please select deposit bank",
                },
                amount: {
                    required: "Please enter request amount",
                },
                paymode: {
                    required: "Please select payment mode",
                },
                ref_no: {
                    required: "Please enter transaction refrence number",
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
                $("[name='gps_location']").val(localStorage.getItem("gps_location"));
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button:submit').button('loading');
                    },
                    complete: function () {
                        form.find('button:submit').button('reset');
                    },
                    success:function(data){
                        if(data.status == "TXN"){
                            form.closest('.modal').modal('hide');
                            SYSTEM.NOTIFY("Fund Request submitted Successfull", 'success');
                            $('#datatable').dataTable().api().ajax.reload();
                        }else{
                            SYSTEM.NOTIFY(data.message , 'warning');
                        }
                    },
                    error: function(errors) {
                        SYSTEM.SHOWERROR(errors, form);
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