@extends('layouts.app')
@section('title', "Complaints")
@section('pagetitle',  "Complaints")

@php
    $table = "yes";
@endphp

@section('content')
    <div class="content">
        <div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default">
                    <div class="panel-heading" style="background: linear-gradient(45deg, #00887b, #00807f); border-radius:5px;">
                        <h4 class="panel-title">Complaints</h4>
                    </div>
                    <table class="table table-bordered table-striped table-hover" id="datatable">
                        <thead>
                            <tr>
                                <th>Transaction Details</th>
                                <th>Subject</th>
                                <th>Utr</th>
                                <th>Solution Details</th>
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
@endsection

@push('script')
    <script type="text/javascript">
        $(document).ready(function () {
            var url = "{{route('reportstatic')}}";;
            $('[name="dataType"]').val("complaint");
            
            var onDraw = function() {
            };
            var options = [
                { "data" : "bank",
                    render:function(data, type, full, meta){
                        return full.product + "<br>Txn Id : " + full.transaction_id;
                    }
                },
                { "data" : "subject"},
                { "data" : "description"},
                { "data" : "solution"},
                { "data" : "status",
                    render:function(data, type, full, meta){
                        if(full.status == "resolved"){
                            var out = `<span class="label label-success">Resolved</span>`;
                        }else if(full.status == "rejected"){
                            var out = `<span class="label label-danger">Rejected</span>`;
                        }else{
                            var out = `<span class="label label-warning">Pending</span>`;
                        }

                        return out;
                    }
                }
            ];

            datatableSetup(url, options, onDraw);

            $( "#bbpscomplaintForm").validate({
                rules: {
                    transaction_id: {
                        required: true,
                    },
                    subject: {
                        required: true,
                    }
                },
                messages: {
                    transaction_id: {
                        required: "Please enter transaction id",
                    },
                    subject: {
                        required: "Please select subject",
                    },
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
                    var form = $('#bbpscomplaintForm');
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
                                form[0].reset();
                                form.find('select').val("").trigger('change');
                                $('#datatable').dataTable().api().ajax.reload();
                                $.alert({
                                    icon: 'fa fa-check',
                                    theme: 'modern',
                                    animation: 'scale',
                                    type: 'green',
                                    title : "Success",
                                    content : "Complaint Register Successfully",
                                });
                            }else{
                                SYSTEM.NOTIFY(data.message , 'warning');
                            }
                        },
                        error: function(errors) {
                            showError(errors, form);
                        }
                    });
                }
            });
        });

        function viewData(id){
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

        function complaintCheck(complaintid){
            $('#complaintTrackModal').find('[name="complaintid"]').val(complaintid);
            $('#complaintTrackModal').find('.complaintid').text(complaintid);
            $('#complaintTrackModal').modal('show');
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