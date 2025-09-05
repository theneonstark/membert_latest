
@extends('layouts.app')
@section('title', "My Invoice")
@section('pagetitle',  "My Invoice")

@php
    $table  = "yes";
    $newexport = "yes";
@endphp

@section('content')
<div class="content p-b-0">
    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title text-capitalize">My Invoice</h4>
                </div>
                <table class="table table-bordered table-striped table-hover" id="datatable">
                    <thead>
                        <tr>
                            <th width="200px">#</th>
                            <th>Month</th>
                            <th>Type</th>
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
<script src="{{ asset('/assets/js/core/jQuery.print.js') }}"></script>
<script type="text/javascript">
    var DT;

    $(document).ready(function () {
        $('[name="dataType"]').val("invoice");
        
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
            { "data" : "name"},
            { "data" : "created_at",
                render: function(data, type, full, meta){
                    return full.month+"-"+full.year;
                }
            },
            { "data" : "type"},
            { "data" : "type",
                render:function(data, type, full, meta){
                    return `<a href="{{url("invoice")}}/`+full.id+`" class="btn btn-xs btn-primary"><i class="icon-eye"></i> View</a>`;
                }
            }
        ];

        DT = datatableSetup(url, options, onDraw);
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