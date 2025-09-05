@extends('layouts.app')
@section('title', 'Scheme Manager')
@section('pagetitle',  'Scheme Manager')
@php
    $table = "yes";
    $agentfilter = "hide";
    $seacrh = "yes";

    $status['type'] = "Scheme";
    $status['data'] = [
        "1" => "Active",
        "0" => "De-active"
    ];
@endphp

@section('content')
<div class="content">
    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">Scheme Manager</h4>
                    <div class="heading-elements">
                        <button type="button" class="btn btn-sm bg-slate btn-raised heading-btn legitRipple" onclick="addSetup()">
                            <i class="icon-plus2"></i> Add New
                        </button>
                    </div>
                </div>
                <table class="table table-bordered table-striped table-hover" id="datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Status</th>
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

<div id="setupModal" class="modal fade" data-backdrop="false" data-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-slate">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h6 class="modal-title"><span class="msg">Add</span> Scheme</h6>
            </div>
            <form id="setupManager" action="{{route('resourceupdate')}}" method="post">
                <div class="modal-body">
                    <div class="row">
                        <input type="hidden" name="id">
                        <input type="hidden" name="actiontype" value="scheme">
                        {{ csrf_field() }}
                        <div class="form-group col-md-12">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter Bank Name" required="">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                    <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                </div>
            </form>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog  modal-lg -->
</div><!-- /.modal -->

@if(isset($commission))
    @foreach($commission as $key => $value)
        <div id="{{$key}}Modal" class="modal fade" role="dialog" data-backdrop="false">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-slate">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                        <h4 class="modal-title">{{$key}} Commission</h4>
                    </div>
                    <form class="commissionForm" method="post" action="{{ route('resourceupdate') }}">
                        <div class="modal-body p-0" style="margin-bottom:20px">
                            {!! csrf_field() !!}
                            <input type="hidden" name="actiontype" value="commission">
                            <input type="hidden" name="service" value="{{$key}}">
                            <input type="hidden" name="scheme_id" value="">                
                            <table class="table table-bordered m-0">
                                <thead>
                                    <th>Operator</th>
                                    @if (Myhelper::hasRole('admin'))
                                        <th>Commission Type
                                            <select class="form-control" onchange="SETTYPE(this)">
                                                <option value="">Select Type</option>
                                                <option value="percent">Percent (%)</option>
                                                <option value="flat">Flat (Rs)</option>
                                            </select>
                                        </th>
                                    @endif
                                    <th>Api Partner
                                        <input type="number" step="any" onkeyup ="SETVALUE('apiuser[]', this)" class="form-control" placeholder="Enter Value">
                                    </th>
                                </thead>
                                <tbody>
                                    @foreach ($value as $element)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="slab[]" value="{{$element->id}}">
                                                {{$element->name}}
                                            </td>
                                            @if (Myhelper::hasRole('admin'))     
                                                <td class="p-t-0 p-b-0">
                                                    <select class="form-control" name="type[]" required="">
                                                        <option value="">Select Type</option>
                                                        <option value="percent">Percent (%)</option>
                                                        <option value="flat">Flat (Rs)</option>
                                                    </select>
                                                </td>
                                            @endif
                                            <td class="p-t-0 p-b-0">
                                                <input type="number" step="any" name="apiuser[]" placeholder="Enter Value" class="form-control" required="">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                            <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div><!-- /.modal -->
    @endforeach
@endif

@foreach($charge as $key => $value)
    <div id="{{$key}}Modal" class="modal fade" role="dialog" data-backdrop="false">
        <div class="modal-dialog  modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                    <h4 class="modal-title">{{$key}} Charge</h4>
                </div>
                <form class="commissionForm" method="post" action="{{ route('resourceupdate') }}">
                    <div class="modal-body p-0" style="margin-bottom:20px">
                        {!! csrf_field() !!}
                        <input type="hidden" name="actiontype" value="commission">
                        <input type="hidden" name="scheme_id" value="">                
                        <table class="table table-bordered m-0">
                            <thead>
                                <th>Operator</th>
                                @if (Myhelper::hasRole('admin'))
                                    <th>Charge Type
                                        <select class="form-control" onchange="SETTYPE(this)">
                                            <option value="">Select Type</option>
                                            <option value="percent">Percent (%)</option>
                                            <option value="flat">Flat (Rs)</option>
                                        </select>
                                    </th>
                                @endif
                                <th>Api Partner
                                    <input type="number" step="any" onkeyup ="SETVALUE('apiuser[]', this)" class="form-control" placeholder="Enter Value">
                                </th>
                            </thead>
                            <tbody>
                                @foreach ($value as $element)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="slab[]" value="{{$element->id}}">
                                            {{$element->name}}
                                        </td>
                                        @if (Myhelper::hasRole('admin'))     
                                            <td class="p-t-0 p-b-0">
                                                <select class="form-control" name="type[]" required="">
                                                    <option value="">Select Type</option>
                                                    <option value="percent">Percent (%)</option>
                                                    <option value="flat">Flat (Rs)</option>
                                                </select>
                                            </td>
                                        @endif
                                        <td class="p-t-0 p-b-0">
                                            <input type="number" step="any" name="apiuser[]" placeholder="Enter Value" class="form-control" required="">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                        <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /.modal -->
@endforeach

<div id="commissionModal" class="modal fade" role="dialog" data-backdrop="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
                <div class="modal-header bg-slate">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                <h4 class="modal-title">Scheme <span class="schemename"></span> Commission/Charge</h4>
            </div>

            <div class="modal-body no-padding commissioData">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
            </div>
        </div>
    </div>
</div><!-- /.modal -->
@endsection

@push('style')
    <style type="text/css">
        .modal-lg{
            width: 90%!important;
        }
    </style>
@endpush

@push('script')
    <script type="text/javascript">
    $(document).ready(function () {
        var url = "{{url('statement/list/fetch')}}/resource{{$type}}/0";
        
        $('input[name="apiuser[]"]').val('0');
        
        var onDraw = function() {
            $('input#schemeStatus').on('click', function(evt){
                evt.stopPropagation();
                var ele    = $(this);
                var id     = $(this).val();
                var status = "0";
                if($(this).prop('checked')){
                    status = "1";
                }
                
                $.ajax({
                    url: '{{ route('resourceupdate') }}',
                    type: 'post',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType:'json',
                    data: {'id':id, 'status':status, "actiontype":"scheme"}
                })
                .done(function(data) {
                    if(data.status == "success"){
                        notify("Scheme Updated", 'success');
                        $('#datatable').dataTable().api().ajax.reload();
                    }else{
                        if(status == "1"){
                            ele.prop('checked', false);
                        }else{
                            ele.prop('checked', true);
                        }
                        notify("Something went wrong, Try again." ,'warning');
                    }
                })
                .fail(function(errors) {
                    if(status == "1"){
                        ele.prop('checked', false);
                    }else{
                        ele.prop('checked', true);
                    }
                    showError(errors, "withoutform");
                });
            });
        };

        var options = [
            { "data" : "id"},
            { "data" : "name"},
            { "data" : "name",
                render:function(data, type, full, meta){
                    var check = "";
                    if(full.status == "1"){
                        check = "checked='checked'";
                    }

                    return `<label class="switch">
                                <input type="checkbox" id="schemeStatus" `+check+` value="`+full.id+`" actionType="`+type+`">
                                <span class="slider round"></span>
                            </label>`;
                }
            },
            { "data" : "id",
                render:function(data, type, full, meta){
                    var menu = ``;
                        @if(isset($commission))
                            menu += `<li class="dropdown-header">Commission</li>`;
                            @foreach($commission as $key => $value)
                            menu += `<li><a href="javascript:void(0)" onclick="commission(`+full.id+`, '{{$key}}','{{$key}}Modal')"><i class="fa fa-inr"></i> {{$key}} Commission</a></li>`;
                            @endforeach
                        @endif

                        menu += `<li class="dropdown-header">Charge</li>`;

                        @foreach($charge as $key => $value)
                        menu += `<li><a href="javascript:void(0)" onclick="commission(`+full.id+`, '{{$key}}','{{$key}}Modal')"><i class="fa fa-inr"></i> {{$key}} Charge</a></li>`;
                        @endforeach

                    var out =  `<button type="button" class="btn bg-slate btn-raised legitRipple btn-xs" onclick="editSetup(this)">Edit</button>
                                <button type="button" class="btn bg-slate btn-raised legitRipple btn-xs" onclick="viewCommission(`+full.id+`, '`+full.name+`')"> View Commission</button>
                                <div class="btn-group btn-group-fade">
                                    <button type="button" class="btn bg-slate btn-raised legitRipple btn-xs" data-toggle="dropdown" aria-expanded="false">Commission/Charge <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right" style="height:280px; overflow-y:auto">
                                        `+menu+`
                                    </ul>
                                </div>`;

                    return out;
                }
            },
        ];
        datatableSetup(url, options, onDraw);

        $( "#setupManager" ).validate({
            rules: {
                name: {
                    required: true,
                }
            },
            messages: {
                name: {
                    required: "Please enter bank name",
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
                var form = $('#setupManager');
                var id = form.find('[name="id"]').val();

                SYSTEM.FORMSUBMIT(form, function(data){
                    if (!data.statusText) {
                        if(data.status == "success"){
                            form.find('button[type="submit"]').button('reset');
                            SYSTEM.NOTIFY("Scheme Successfully Updated", 'success');
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

        $('form.commissionForm').submit(function(){
            var form= $(this);
            form.closest('.modal').find('tbody').find('span.pull-right').remove();
            $(this).ajaxSubmit({
                dataType:'json',
                beforeSubmit:function(){
                    form.find('button[type="submit"]').button('loading');
                },
                complete: function(){
                    form.find('button[type="submit"]').button('reset');
                },
                success:function(data){
                    $.each(data.status, function(index, values) {
                        if(values.id){
                            form.find('input[value="'+index+'"]').closest('tr').find('td').eq(0).append('<span class="pull-right text-success"><i class="fa fa-check"></i></span>');
                        }else{
                            form.find('input[value="'+index+'"]').closest('tr').find('td').eq(0).append('<span class="pull-right text-danger"><i class="fa fa-times"></i></span>');
                            if(values != 0){
                                form.find('input[value="'+index+'"]').closest('tr').find('input[name="apiuser[]"]').closest('td').append('<span class="text-danger pull-right"><i class="fa fa-times"></i> '+values+'</span>');
                            }
                        }
                    });
    
                    setTimeout(function () {
                        form.find('span.pull-right').remove();
                    }, 10000);
                },
                error: function(errors) {
                    showError(errors, form);
                }
            });
            return false;
        });

        $("#setupModal").on('hidden.bs.modal', function () {
            $('#setupModal').find('.msg').text("Add");
            $('#setupModal').find('form')[0].reset();
        });
    
    });

    function addSetup(){
        $('#setupModal').find('.msg').text("Add");
        $('#setupModal').find('input[name="id"]').val("new");
        $('#setupModal').modal('show');
    }

    function editSetup(ele){
        var id = $(ele).closest('tr').find('td').eq(0).text();
        var name = $(ele).closest('tr').find('td').eq(1).text();

        $('#setupModal').find('.msg').text("Edit");
        $('#setupModal').find('input[name="id"]').val(id);
        $('#setupModal').find('input[name="name"]').val(name);
        $('#setupModal').modal('show');
    }
    
    function commission(id, type, modal) {
        $.ajax({
            url: '{{ url('company/resources/get') }}/'+type+"/commission",
            type: 'post',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType:'json',
            data:{'scheme_id':id}
        })
        .done(function(data) {
            if(data.length > 0){
                $.each(data, function(index, values) {
                    $('#'+modal).find('input[value="'+values.slab+'"]').closest('tr').find('input[name="apiuser[]"]').val(values.apiuser);
                });
            }
        })
        .fail(function(errors) {
            SYSTEM.SHOWERROR('Oops', errors.status+'! '+errors.statusText, 'warning');
        });
    
        $('#'+modal).find('input[name="scheme_id"]').val(id);
        $('#'+modal).modal();
    }

    @if(isset($mydata['schememanager']) && $mydata['schememanager']->value == "all")
        function viewCommission(id, name) {
            if(id != ''){
                $.ajax({
                    url: '{{route("getMemberPackageCommission")}}',
                    type: 'post',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data : {"scheme_id" : id},
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
                    $('#commissionModal').find('.schemename').text(name);
                    $('#commissionModal').find('.commissioData').html(data);
                    $('#commissionModal').modal('show');
                })
                .fail(function() {
                    swal.close();
                    SYSTEM.SHOWERROR('Somthing went wrong', 'warning');
                });
            }
        }
    @else
    function viewCommission(id, name) {
        if(id != ''){
            $.ajax({
                url: '{{route("getMemberCommission")}}',
                type: 'post',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data : {"scheme_id" : id},
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
                $('#commissionModal').find('.schemename').text(name);
                $('#commissionModal').find('.commissioData').html(data);
                $('#commissionModal').modal('show');
            })
            .fail(function() {
                swal.close();
                notify('Somthing went wrong', 'warning');
            });
        }
    }
    @endif

    function SETTYPE(ele){
        var type = $(ele).val();
        $(ele).closest("table").find('[name="type[]"]').select2().val(type).trigger('change');
    }

    function SETVALUE(set ,ele){
        var value = $(ele).val();
        $(ele).closest("table").find('[name="'+set+'"]').val(value);
    }
</script>
@endpush