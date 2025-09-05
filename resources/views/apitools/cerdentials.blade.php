@extends('layouts.app')
@section('title', "Api Setting")
@section('pagetitle', "Api Setting")

@php
    $table = "yes";
    $search = "yes";
@endphp

@section('content')
<div class="content">
    <div class="page-header">
        <div class="page-header-content no-padding">
            <div class="page-title">
                <h5>Developer Tools</h5>
                <a class="heading-elements-toggle"><i class="icon-more"></i></a>
            </div>

            <div class="heading-elements" style="right: 0px;">
                <div class="heading-btn-group">
                    <a  href="https://upiapi.readme.io/reference/api-credentials" target="_blank" class="legitRipple">
                        <button type="button" class="btn btn-primary active legitRipple">Api Documents</button>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                    <h4 class="panel-title">Api Tokens</h4>
                    <div class="heading-elements">
                        <button type="button" class="btn btn-xs btn-danger btn-raised heading-btn legitRipple" onclick="generateToken()">
                            <i class="icon-plus2"></i> Generate Token
                        </button>
                    </div>
                </div>
                
                <table class="table table-striped table-hover" id="datatable">
                    <thead>
                        <tr>
                            <th>Partner Id</th>
                            <th>Api Key</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius: 5px;">
                    <h4 class="panel-title">Whitelisted IP</h4>
                    <div class="heading-elements">
                        <!--<button type="button" data-toggle="modal" data-target="#setupModal" class="btn btn-xs btn-danger btn-raised heading-btn legitRipple">-->
                        <!--    <i class="icon-plus2"></i> New Ip-->
                        <!--</button>-->
                    </div>
                </div>
                
                <table class="table table-striped table-hover" id="ipdatatable">
                    <thead>
                        <tr>
                            <th>Ip</th>
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
                <h6 class="modal-title">Whitelist Your IP</h6>
            </div>
            <form id="setupManager" action="{{route('apiiptore')}}" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label>IP</label>
                        <input type="text" name="ip" class="form-control" placeholder="Enter your server ip" required="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                    <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Submitting">Add Token</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="credentialModal" class="modal fade" data-backdrop="false" data-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-slate">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h6 class="modal-title">Api Credentials</h6>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">
                {{ csrf_field() }}
                <div class="form-group">
                    <label>Partner ID</label>
                    <input type="text" name="user_id" class="form-control" placeholder="" required="">
                </div>

                <div class="form-group">
                    <label>Api Key</label>
                    <input type="text" name="api_key" class="form-control" placeholder="" required="">
                </div>

                <p class="text-danger">Note : Kindly save these credentials after close popup credentials will not show again</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                <a class="download" target="_blank">
                    <button type="button" class="btn btn-primary btn-raised legitRipple">Download</button>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

<script type="text/javascript">
    $(document).ready(function () {
        var url     = "{{url('statement/list/fetch')}}/apitoken/0";
        var onDraw  = function() {};

        var options = [
            { "data" : "user_id"},
            { "data" : "api_key"},
            { "data" : "api_key",
                render:function(data, type, full, meta){
                    return `<button type="button" class="btn bg-danger btn-raised legitRipple btn-xs" onclick="deleteToken(`+full.id+`)"> <i class="fa fa-trash"></i></button>`;
                }
            },
        ];
        datatableSetup(url, options, onDraw);

        var ipurl = "{{url('statement/list/fetch')}}/whitelistedip/0";
        var iponDraw = function() {};

        var ipoptions = [
            { "data" : "ip"},
            { "data" : "ip",
                render:function(data, type, full, meta){
                    return `<button type="button" class="btn bg-danger btn-raised legitRipple btn-xs" onclick="deleteIp(`+full.id+`)"> <i class="fa fa-trash"></i></button>`;
                }
            }
        ];

        datatableSetup(ipurl, ipoptions, iponDraw, "#ipdatatable");

        $( "#setupManager" ).validate({
            rules: {
                ip: {
                    required: true,
                }
            },
            messages: {
                ip: {
                    required: "Please enter ip",
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
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button[type="submit"]').button('loading');
                    },
                    success:function(data){
                        if(data.statuscode == "TXN"){
                            form[0].reset();
                            form.closest('.modal').modal('hide');
                            form.find('button[type="submit"]').button('reset');
                            SYSTEM.NOTIFY("IP Successfully Whitelisted", 'success');
                            $('#ipdatatable').dataTable().api().ajax.reload();
                        }else if(data.statuscode == "TXNOTP"){
                            form.find('button[type="submit"]').button('reset');
                            SYSTEM.NOTIFY("Otp sent on your registered mobile number", 'success');
                            $("#setupManager").find(".modal-body").append(`<div class="form-group">
                                <label>Otp</label>
                                <input type="text" name="otp" class="form-control" placeholder="Enter otp" required="">
                            </div>`);
                        }else{
                            SYSTEM.NOTIFY(data.status, 'warning');
                        }
                    },
                    error: function(errors) {
                        showError(errors, form);
                    }
                });
            }
        });

        $( "#callbackForm" ).validate({
            rules: {
                callback: {
                    required: true,
                }
            },
            messages: {
                callback: {
                    required: "Please enter callback url",
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
                var form = $('#callbackForm');
                var id = form.find('[name="id"]').val();
                form.ajaxSubmit({
                    dataType:'json',
                    beforeSubmit:function(){
                        form.find('button[type="submit"]').button('loading');
                    },
                    success:function(data){
                        if(data.status == "success"){
                            form.find('button[type="submit"]').button('reset');
                            SYSTEM.NOTIFY("Callback Successfully Updated", 'success');
                            $('#datatable').dataTable().api().ajax.reload();
                        }else{
                            SYSTEM.NOTIFY(data.status, 'warning');
                        }
                    },
                    error: function(errors) {
                        showError(errors, form);
                    }
                });
            }
        });
    });

    function addSetup(){
    	$('#setupModal').find('.msg').text("Add");
    	$('#setupModal').find('input[name="id"]').val("new");
    	$('#setupModal').modal('show');
    }
    
    function generateToken(){
        $.ajax({
            url: "{{ route('apitokenstore') }}",
            type: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            beforeSend:function(){
                swal({
                    title: 'Wait!',
                    text: 'Please wait, we are fetching transaction details',
                    onOpen: () => {
                        swal.showLoading()
                    },
                    allowOutsideClick: () => !swal.isLoading()
                });
            },
            dataType:'json',
            success: function(result){
                swal.close();
                if(result.status == "success"){
                    $("#credentialModal").find("[name='api_key']").val(result.data.api_key);
                    $("#credentialModal").find("[name='aes_key']").val(result.data.aes_key);
                    $("#credentialModal").find("[name='aes_iv']").val(result.data.aes_iv);
                    $("#credentialModal").find("[name='user_id']").val(result.data.user_id);
                    $("#credentialModal").find(".download").attr("href", "{{url("developer/api/download")}}?id="+result.data.id);
                    $("#credentialModal").modal("show");
                    SYSTEM.NOTIFY("Callback Successfully Updated", 'success');
                    $('#datatable').dataTable().api().ajax.reload();
                }else{
                    SYSTEM.NOTIFY(data.status, 'warning');
                }
            },
            error: function(error){
                swal.close();
                showError(error);
            }
        });
    }
    
    function deleteToken(id){
        swal({
            title: 'Are you sure ?',
            text: "You want to delete token",
            type: 'warning',
            showCancelButton: true,
            confirmButtonClass: "btn-danger",
            confirmButtonText: 'Yes delete it!',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !swal.isLoading(),
            preConfirm: () => {
                return new Promise((resolve) => {
                    $.ajax({
                        url: "{{ route('tokenDelete') }}",
                        type: "POST",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        data: {'id':id},
                        success: function(result){
                            resolve(result);
                        },
                        error: function(error){
                            resolve(error);
                        }
                    });
                });
            },
        }).then((result) => {
            if(result.value.status == "1"){
                SYSTEM.NOTIFY("Token Successfully Deleted", 'success');
                $('#datatable').dataTable().api().ajax.reload();
            }else{
                SYSTEM.NOTIFY('Something went wrong, try again', 'error');
            }
        });
    }
    
    function deleteIp(id){
        swal({
            title: 'Are you sure ?',
            text: "You want to delete ip",
            type: 'warning',
            showCancelButton: true,
            confirmButtonClass: "btn-danger",
            confirmButtonText: 'Yes delete it!',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !swal.isLoading(),
            preConfirm: () => {
                return new Promise((resolve) => {
                    $.ajax({
                        url: "{{ route('ipDelete') }}",
                        type: "POST",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        data: {'id':id},
                        success: function(result){
                            resolve(result);
                        },
                        error: function(error){
                            resolve(error);
                        }
                    });
                });
            },
        }).then((result) => {
            if(result.value.status == "1"){
                SYSTEM.NOTIFY("IP Successfully Deleted", 'success');
                $('#ipdatatable').dataTable().api().ajax.reload();
            }else{
                SYSTEM.NOTIFY('Something went wrong, try again', 'Oops', 'error');
            }
        });
    }
</script>
@endpush
