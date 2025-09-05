@extends('layouts.app')
@section('title', ucwords($type).' List')
@section('pagetitle',  ucwords($type).' List')

@php
    $table = "yes";
    $export = $type;

    $table = "yes";

    switch($type){
        case 'kycpending':
        case 'kycsubmitted':
        case 'kycrejected':
            $status['type'] = "Kyc";
            $status['data'] = [
                "pending"   => "Pending",
                "submitted" => "Submitted",
                "verified"  => "Verified",
                "rejected"  => "Rejected",
            ];
        break;

        default:
            $status['type'] = "";
            $status['data'] = [
                "active" => "Active",
                "block"  => "Block"
            ];
        break;
    }
@endphp

@section('content')
    <div class="content">
        @include('layouts.filter')
        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default">
                    <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                        <h4 class="panel-title"><span class="titleName"></span> List</h4>
                        <div class="heading-elements">
                            <a href="{{route('member', ['type' => $type, 'action' => 'create'])}}">
                                <button type="button" class="btn btn-sm bg-slate btn-raised heading-btn legitRipple">
                                    <i class="icon-plus2"></i> Add New
                                </button>
                            </a>
                        </div>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Parent Details</th>
                                <th>Company Profile</th>
                                <th>Wallet Details</th>
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

    <div id="commissionModal" class="modal fade right" role="dialog" data-backdrop="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                    <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title">Scheme Manager</h4>
                </div>
                <form id="schemeForm" method="post" action="{{ route('profileUpdate') }}">
                    <div class="modal-body">
                        {!! csrf_field() !!}
                        <input type="hidden" name="id">
                        <input type="hidden" name="actiontype" value="scheme">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Scheme</label>
                                <select class="form-control select" name="scheme_id" required="" onchange="viewCommission(this)">
                                    <option value="">Select Scheme</option>
                                    @foreach ($scheme as $element)
                                        <option value="{{$element->id}}">{{$element->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Security Pin</label>
                                <input type="password" name="mpin" autocomplete="off" class="form-control" required="">
                            </div>
                            <div class="form-group col-md-4">
                                <label style="width:100%">&nbsp;</label>
                                <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                                <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="modal-body no-padding commissioData">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="transferModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Fund Transfer / Return</h6>
                </div>
                <form id="transferForm" action="{{route('fundtransaction')}}" method="post">
                    <div class="modal-body">
                        <div class="row">
                            <input type="hidden" name="payee_id">
                            {{ csrf_field() }}
                            <input type="hidden" name="wallet" value="mainwallet">
                            <div class="form-group col-md-6">
                                <label>Fund Action</label>
                                <select name="type" class="form-control select" id="select" required>
                                    <option value="">Select Action</option>
                                    @if (Myhelper::can('fund_transfer'))
                                        <option value="transfer">Transfer</option>
                                    @endif
                                    @if (Myhelper::can('fund_return'))
                                        <option value="return">Return</option>
                                    @endif
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Amount</label>
                                <input type="number" name="amount" step="any" class="form-control" placeholder="Enter Amount" required="">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Refno</label>
                                <input type="text" name="refno" step="any" class="form-control" placeholder="Enter Utr" required="">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label>Remark</label>
                                <textarea name="remark" class="form-control" rows="3" placeholder="Enter Remark"></textarea>
                            </div>
                        </div>

                        @if(Myhelper::hasRole('admin'))
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Security Pin</label>
                                    <input type="password" name="mpin" autocomplete="off" class="form-control" required="">
                                </div>
                            </div>
                        @endif
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

@push('script')
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/forms/selects/select2.min.js"></script>
    <script type="text/javascript">
        var DT, url="", onDraw, options, transtype = "";

        $(document).ready(function () {
            $('.select').select2();
            var url = "{{url('statement/list/fetch')}}/{{$type}}/0";

            onDraw = function() {
                $('input#membarStatus').on('click', function(evt){
                    evt.stopPropagation();
                    var ele = $(this);
                    var id = $(this).val();
                    var status = "block";
                    if($(this).prop('checked')){
                        status = "active";
                    }
                    
                    $.ajax({
                        url: '{{ route('profileUpdate') }}',
                        type: 'post',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        data: {'id':id, 'status':status, 'actiontype' : 'status'}
                    })
                    .done(function(data) {
                        if(data.status == "success"){
                            notify("Member Updated", 'success');
                            $('#datatable').dataTable().api().ajax.reload();
                        }else{
                            if(status == "active"){
                                ele.prop('checked', false);
                            }else{
                                ele.prop('checked', true);
                            }
                            notify("Something went wrong, Try again." ,'warning');
                        }
                    })
                    .fail(function(errors) {
                        if(status == "active"){
                            ele.prop('checked', false);
                        }else{
                            ele.prop('checked', true);
                        }
                        showError(errors, "withoutform");
                    });
                });
            };

            options = [
                { "data" : "name",
                    'className' : "notClick",
                    render:function(data, type, full, meta){
                        var check = "";
                        var type = "status";
                        var tag = "";
                        if(full.status == "active"){
                            check = "checked='checked'";
                        }

                        if(full.tag == "premium"){
                            tag = `<label class="label label-primary mb-10">Premium</label>`;
                        }

                        return `<div>
                                `+tag+`
                                <label class="switch">
                                    <input type="checkbox" id="membarStatus" `+check+` value="`+full.id+`" actionType="`+type+`">
                                    <span class="slider round"></span>
                                </label>
                                <span class='text-inverse pull-right m-l-10'><b>`+full.id +`</b> </span>
                                <div class="clearfix"></div>
                            </div>
                            <span style='font-size:13px'>`+full.created_at+`</span>`;
                    }
                },
                { "data" : "name",
                    render:function(data, type, full, meta){
                        return `<span class="name">`+full.name+`</span>` +`<br>`+full.email +`<br>`+full.mobile;
                    }
                },
                { "data" : "parents"},
                { "data" : "name",
                    render:function(data, type, full, meta){
                        var out = `<span class="name">`+full.company.companyname+`</span>` +`<br>`+full.company.website;
                        return out;
                    }
                },
                { "data" : "name",
                    render:function(data, type, full, meta){
                        return `Main Wallet: `+full.mainwallet +`<br>Collection Wallet: `+full.collectionwallet +`<br>Qr Wallet: `+full.qrwallet +`<br>RR Wallet : `+full.cbwallet+`<br>Locked Amt : `+full.lockedwallet;
                    }
                },
                { "data" : "id",
                    render:function(data, type, full, meta){
                        var out = '';
                        var menu = ``;

                        @if (Myhelper::can(['fund_transfer']))
                            menu += `<li class="dropdown-header">Action</li><li><a href="javascript:void(0)" onclick="transfer(`+full.id+`)"><i class="icon-wallet"></i> Fund Transfer / Return</a></li>`;
                        @endif

                        menu += `<li><a href="javascript:void(0)" onclick="scheme(`+full.id+`, '`+full.scheme_id+`')"><i class="icon-wallet"></i> Scheme</a></li>`;

                        @if (Myhelper::can('member_profile_view'))
                            menu += `<li><a href="{{url('member/profile/view')}}/`+full.id+`" target="_blank"><i class="icon-user"></i> View Profile</a></li>`;
                        @endif
                        
                        out +=  `<ul class="icons-list">
                                    <li class="dropdown">
                                        <a href="#" class="dropdown-toggle mt-10" data-toggle="dropdown">
                                            <span class="label bg-slate">Action <i class="icon-arrow-down5"></i></span>
                                        </a>

                                        <ul class="dropdown-menu dropdown-menu-right">
                                            `+menu+`
                                        </ul>
                                    </li>
                                </ul>`;
                        
                        var out2 = '';
                        var menu2 = ``;

                        menu2 += `<li><a href="{{url('statement/report/payin/')}}/`+full.id+`" target="_blank"><i class="icon-paragraph-justify3"></i> Transaction</a></li>`;
                        menu2 += `<li><a href="{{url('statement/report/ledger/')}}/`+full.id+`" target="_blank"><i class="icon-paragraph-justify3"></i> Ledger</a></li>`;

                        out2 +=  `<ul class="icons-list">
                                    <li class="dropdown">
                                        <a href="#" class="dropdown-toggle mt-10" data-toggle="dropdown">
                                            <span class="label bg-slate">Reports <i class="icon-arrow-down5"></i></span>
                                        </a>

                                        <ul class="dropdown-menu dropdown-menu-right">
                                            `+menu2+`
                                        </ul>
                                    </li>
                                </ul>`;
                        return out+out2;
                    }
                }
            ];

            DT = datatableSetup(url, options, onDraw);

            $( "#schemeForm").validate({
                rules: {
                    scheme_id: {
                        required: true
                    }
                },
                messages: {
                    scheme_id: {
                        required: "Please select scheme",
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
                    var form = $('#schemeForm');
                    var type = $('#schemeForm').find('[name="type"]').val();

                    SYSTEM.FORMSUBMIT(form, function(data){
                        if (!data.statusText) {
                            if(data.status == "TXN"){
                                form.find('button[type="submit"]').button('reset');
                                SYSTEM.NOTIFY("Member Scheme Updated Successfull", 'success');
                                $('#datatable').dataTable().api().ajax.reload();
                            }else{
                                SYSTEM.NOTIFY(data.message, "error");
                            }
                        } else {
                            SYSTEM.SHOWERROR(data, form);
                        }
                    });
                }
            });
        });

        function scheme(id, scheme){
            $('#schemeForm').find('[name="id"]').val(id);
            if(scheme != '' && scheme != null && scheme != 'null'){
                $('#schemeForm').find('[name="scheme_id"]').select2().val(scheme).trigger('change');
            }
            $('#commissionModal').modal();
        }

        function viewCommission(element) {
            var scheme_id = $(element).val();
            if(scheme_id != '' && scheme_id != 0){
                $.ajax({
                    url: '{{route("getMemberCommission")}}',
                    type: 'post',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data : {"scheme_id" : scheme_id},
                    beforeSend : function(){
                        swal({
                            title: 'Wait!',
                            text: 'Please wait, we are fetching commission details',
                            onOpen: () => {
                                swal.showLoading()
                            },
                            allowOutsideClick: () => !swal.isLoading()
                        });
                    }
                })
                .success(function(data) {
                    swal.close();
                    $('#commissionModal').find('.commissioData').html(data);
                })
                .fail(function() {
                    swal.close();
                    notify('Somthing went wrong', 'warning');
                });
            }
        }
    </script>
@endpush