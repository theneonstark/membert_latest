<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <link href="https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/icons/icomoon/styles.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/icons/fontawesome/styles.min.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/bootstrap.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/core.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/components.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/colors.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/js/plugins/materialToast/mdtoast.min.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/css/jquery-confirm.min.css" rel="stylesheet" type="text/css">
    <link href="{{asset('')}}assets/js/plugins/waitMe/waitMe.css" rel="stylesheet" type="text/css">
    <style>
        .bg-material{
            background-color: #2a2a2a;
        }
        
        .sidebar-default .navigation li > ul > li > a{
            color : white;
        }
        
        .sidebar-default .navigation > li ul{
            background-color: #00897B;
        }

        .navbar-inverse {
            background-color: #00897B !important;
            border-color: #00897B !important;
        }

        .navigation > li.active > a {
            color: #fff !important;
            background-color: #00897B !important;
        }

        .panel-default > .panel-heading{
            color: #fff !important;
            background-color: #2a2a2a;
        }
        
        .newservice{
            background-image: url(http://e-banker.in/assets/new.png);
            background-size: 60px;
            background-repeat: no-repeat;
            background-position: -5px -9px;
            padding-left: 35px !important;
        }

        .sidebar-default .navigation li.active > a,
        .sidebar-default .navigation li.active > a:hover,
        .sidebar-default .navigation li.active > a:focus {
          color: #2a2a2a;
        }

        .navigation li a > i {
          float: left;
          top: 0;
          margin-top: 2px;
          margin-right: 15px;
          -webkit-transition: opacity 0.2s ease-in-out;
          -o-transition: opacity 0.2s ease-in-out;
          transition: opacity 0.2s ease-in-out;
          color: #00897B;
        }

        /*.navigation > li.active .hidden-ul:before {
            border-top: 7px solid {{$mydata['sidebarlightcolor']->value ?? '#259dab'}};
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            content: "";
            display: inline-block;
            position: absolute;
            left: 22px;
            top: 44px;
            z-index: 999;
        }*/

        p.error{
            color: #F44336;
        }
        .changePic{
            position: absolute;
            width: 100%;
            height: 30%;
            left: 0px;
            bottom: 0px;
            background: #fff;
            color: #000;
            padding: 20px 0px;
            line-height: 0px;
        }
        .companyname{
            font-size: 20px;
        }
        .navbar-brand{
            padding: 20px;
            height: 100%!important;
        }
        .modal{
            overflow: auto;
        }
        .news {
            background-color: #000;
            padding: 12px;
            font-size: 22px;
            color: white;
            text-transform: capitalize;
            border-radius: 3px;
            text-align: center;
        }
        .animationClass {
            animation: blink 1.5s linear infinite;
            -webkit-animation: blink 1.5s linear infinite;
            -moz-animation: blink 1.5s linear infinite;
            -o-animation: blink 1.5s linear infinite;
        }

        table{
            width: 100% !important;
        }

        .news:hover .animationClass{
            opacity: 1!important;
            -webkit-animation-play-state: paused;
            -moz-animation-play-state: paused;
            -o-animation-play-state: paused;
            animation-play-state: paused;
        }
          
        @keyframes blink{
            30%{opacity: .30;}
            50%{opacity: .5;}
            75%{opacity: .75;}
            100%{opacity: 1;}
        }

        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
         
        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>

    <style>
        /* width */
        ::-webkit-scrollbar {
          width: 10px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
          background: #f1f1f1; 
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #888; 
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #555; 
        }

        .nav-tabs.nav-tabs-component > .active > a:after, .nav-tabs.nav-tabs-component > .active > a:hover:after, .nav-tabs.nav-tabs-component > .active > a:focus:after {
            background-color: #1976D2;
        }

        .nav-tabs.nav-tabs-component > li > a:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .nav-tabs[class*=bg-]>.active>a, .nav-tabs[class*=bg-]>.active>a:hover, .nav-tabs[class*=bg-]>.active>a:focus {
            background-color: rgba(0, 0, 0, 0.8);
            border-width: 0;
            color: #fff;
        }
    </style>

    @stack('style')
    <!-- Core JS files -->
	<script type="text/javascript" src="{{asset('')}}assets/js/plugins/loaders/pace.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/libraries/jquery.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/libraries/bootstrap.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/loaders/blockui.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/ui/ripple.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.validate.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery.form.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/sweetalert2.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/forms/selects/select2.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
    <script type="text/javascript" src="{{ asset('/assets/js/core/jQuery.print.js') }}"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/jquery-confirm.min.js"></script>
    
    @if (isset($table) && $table == "yes")
        <script type="text/javascript" src="{{asset('')}}assets/js/plugins/tables/datatables/datatables.min.js"></script>
    @endif

    <script type="text/javascript" src="{{asset('')}}assets/js/core/app.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/dropzone.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/materialToast/mdtoast.min.js"></script>
    <script src="{{asset('')}}assets/js/crytojs/cryptojs-aes-format.js"></script>
    <script src="{{asset('')}}assets/js/crytojs/cryptojs-aes.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/forms/styling/uniform.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/waitMe/waitMe.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/core/moment.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function(){
            $(".styled, .multiselect-container input").uniform({
                radioClass: 'choice'
            });

            $('.select').select2();

            $(".navbar-default a").each(function() {
                if (this.href == window.location.href) {
                    $(this).addClass("active");
                    $(this).parent().addClass("active");
                    $(this).parent().parent().parent().addClass("active");
                    $(this).parent().parent().parent().parent().parent().parent().parent().addClass("active");
                }
            });

            $('#newreportExport').click(function(){
                var type     = $('[name="dataType"]').val();
                var fromdate =  $('#searchForm').find('input[name="from_date"]').val();
                var todate   =  $('#searchForm').find('input[name="to_date"]').val();
                var agent    =  $('#searchForm').find('input[name="agent"]').val();
                var status   =  $('#searchForm').find('[name="status"]').val();
                var product  =  $('#searchForm').find('[name="product"]').val();
                var company  =  $('#searchForm').find('[name="company"]').val();
                var via      =  $('#searchForm').find('[name="via"]').val();

                @if(isset($id))
                    agent = "{{$id}}";
                @endif

                if(agent == 0){
                    agent =  $('#searchForm').find('input[name="agent"]').val();
                }

                window.location.href = "{{ url('export/report') }}/"+type+"?fromdate="+fromdate+"&todate="+todate+"&agent="+agent+"&status="+status+"&product="+product+"&company="+company+"&via="+via;
            });

            $('#reportExport').click(function(){
                var type = $(this).attr('product');
                var datatype = $('[name="dataType"]').val();
                var fromdate =  $('#searchForm').find('input[name="from_date"]').val();
                var todate   =  $('#searchForm').find('input[name="to_date"]').val();
                var agent    =  $('#searchForm').find('input[name="agent"]').val();
                var status   =  $('#searchForm').find('[name="status"]').val();
                var product  =  $('#searchForm').find('[name="product"]').val();
                var company  =  $('#searchForm').find('[name="company"]').val();
                var via      =  $('#searchForm').find('[name="via"]').val();

                @if(isset($id) && is_numeric($id))
                    agent = "{{$id}}";
                @endif

                if(agent == 0){
                    agent =  $('#searchForm').find('input[name="agent"]').val();
                }

                window.location.href = "{{ url('export/statement') }}/"+type+"?fromdate="+fromdate+"&todate="+todate+"&agent="+agent+"&status="+status+"&product="+product+"&datatype="+datatype+"&company="+company+"&via="+via;
            });

            $('.mydate').datepicker({
                'autoclose':true,
                'clearBtn':true,
                'todayHighlight':true,
                'format':'yyyy-mm-dd'
            });

            $('input[name="from_date"]').datepicker("setDate", new Date());
            $('input[name="to_date"]').datepicker('setStartDate', new Date());

             $('input[name="to_date"]').focus(function(){
                if($('input[name="from_date"]').val().length == 0){
                    $('input[name="to_date"]').datepicker('hide');
                    $('input[name="from_date"]').focus();
                }
            });

            $('input[name="from_date"]').datepicker().on('changeDate', function(e) {
                $('input[name="to_date"]').datepicker('setStartDate', $('input[name="from_date"]').val());
                $('input[name="to_date"]').datepicker('setDate', $('input[name="from_date"]').val());
            });

            $('form#searchForm').submit(function(){
                var fromdate =  $(this).find('input[name="from_date"]').val();
                var todate   =  $(this).find('input[name="to_date"]').val();
                var dataType =  $('input[name="dataType"]').val();
                if(dataType != ""){
                    $('#searchForm').find('button:submit').button('loading');
                    if(fromdate.length !=0 || todate.length !=0){
                        $('#datatable').dataTable().api().ajax.reload();
                    }
                }

                return false;
            });

            $('#formReset').click(function () {
                $('form#searchForm')[0].reset();
                $('form#searchForm').find('[name="from_date"]').datepicker().datepicker("setDate", new Date());
                $('form#searchForm').find('[name="to_date"]').datepicker().datepicker("setDate", null);
                $('form#searchForm').find('select').select2().val(null).trigger('change')
                $('#formReset').button('loading');
                $('#datatable').dataTable().api().ajax.reload();
            });
            
            $('select').change(function(event) {
                var ele = $(this);
                if(ele.val() != ''){
                    $(this).closest('div.form-group').find('p.error').remove();
                }
            });

            $(".modal").on('hidden.bs.modal', function () {
                if($(this).find('form').length){
                    $(this).find('form')[0].reset();
                }
    
                if($(this).find('.select').length){
                    $(this).find('.select').val(null).trigger('change');
                }
            });

            $( "#complaintForm").validate({
                errorElement: "p",
                errorPlacement: function ( error, element ) {
                    if ( element.prop("tagName").toLowerCase() === "select" ) {
                        error.insertAfter( element.closest( ".form-group" ).find(".select2") );
                    } else {
                        error.insertAfter( element );
                    }
                },
                submitHandler: function () {
                    var form = $('#complaintForm');
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
                                SYSTEM.NOTIFY("Request successfully completed", 'success');
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

        function showData(data) {
            switch(data.product){
                case "payin":
                case "payout":
                    $("div.amountData").html(`<table class="text-center">
                        <thead>
                            <tr>
                                <th>Opening Balance</th>
                                <th>Transaction Amount</th>
                                <th>Charge</th>
                                <th>Gst</th>
                                <th>Closing</th>
                            </tr>

                            <tr>
                                <td class="balance"></td>
                                <td class="amount"></td>
                                <td class="charge"></td>
                                <td class="gst"></td>
                                <td class="closing"></td>
                            </tr>
                        </thead>
                    </table>`);
                    break;

                case "fund transfer":
                    $("div.amountData").html(`<table class="text-center">
                        <thead>
                            <tr>
                                <th>Opening Balance</th>
                                <th>Transaction Amount</th>
                                <th>Closing</th>
                            </tr>

                            <tr>
                                <td class="balance"></td>
                                <td class="amount"></td>
                                <td class="closing"></td>
                            </tr>
                        </thead>
                    </table>`);
                    break;
            }

            switch(data.product){
                case "payin":
                    $("div.transactionData").html(`
                        <div class="form-group">
                            <label class="text-semibold">Payment Upi </label>
                            <span class="pull-right-sm number"></span>
                        </div>

                        <div class="form-group">
                            <label class="text-semibold">Merchant Unique ID</label>
                            <span class="pull-right-sm apitxnid"></span>
                        </div>`
                    );
                    break;

                case "payout":
                    if(data.option1 == "bank"){

                    }else{
                        $("div.transactionData").html(`
                            <div class="form-group">
                                <label class="text-semibold">Transfer To : </label>
                                <span class="pull-right-sm option1"></span>
                            </div>`
                        );
                    }
                    break;

                case "fund transfer":
                    $("div.transactionData").html(`
                        <div class="form-group">
                            <label class="text-semibold">Transfer From : </label>
                            <span class="pull-right-sm option1"></span>
                        </div>`
                    );
                    break;
            }

            $.each(data, function(index, values) {
                switch(index){
                    case "created_at":
                        $("."+index).text(moment(values, "YYYY-MM-DD HH:mm:ss").format("DD MMM YYYY HH:mm:ss"));
                        break;

                    default:
                        $("."+index).text(values);
                        break;
                }
            });

            $("#transactionModal").modal("show");
        }

        function getbalance(){
            $.ajax({
                url: "{{route('getbalance')}}",
                type: "GET",
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType:'json',
                success: function(result){
                    $.each(result, function (index, value) {
                        $('.'+index).text(value);
                    });
                }
            });
        }

        @if (isset($table) && $table == "yes")
            function datatableSetup(urls, datas, onDraw=function () {}, ele="#datatable", element={}) {
                var options = {
                    dom: '<"datatable-header"l><"datatable-scroll"t><"datatable-footer"ip>',
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    ordering:   false,
                    stateSave:  true,
                    lengthMenu: [10, 25, 50, 100],
                    language: {
                        paginate: { 'first': 'First', 'last': 'Last', 'next': '&rarr;', 'previous': '&larr;' }
                    },
                    drawCallback: function () {
                        $(this).find('tbody tr').slice(-3).find('.dropdown, .btn-group').addClass('dropup');
                    },
                    preDrawCallback: function() {
                        $(this).find('tbody tr').slice(-3).find('.dropdown, .btn-group').removeClass('dropup');
                    },    
                    ajax:{
                        url : urls,
                        type: "post",
                        data:function( d )
                            {
                                d._token   = $('meta[name="csrf-token"]').attr('content');
                                d.type     = $('[name="dataType"]').val();
                                d.fromdate = $('#searchForm').find('[name="from_date"]').val();
                                d.todate   = $('#searchForm').find('[name="to_date"]').val();
                                d.searchtext = $('#searchForm').find('[name="searchtext"]').val();
                                d.agent    = $('#searchForm').find('[name="agent"]').val();
                                d.status   = $('#searchForm').find('[name="status"]').val();
                                d.product  = $('#searchForm').find('[name="product"]').val();
                            },
                        beforeSend: function(){
                        },
                        complete: function(){
                            $('#searchForm').find('button:submit').button('reset');
                            $('#formReset').button('reset');
                        },
                        error:function(response) {
                        }
                    },
                    columns: datas
                };

                $.each(element, function(index, val) {
                    options[index] = val; 
                });

                var DT = $(ele).DataTable(options).on('draw.dt', onDraw);
                return DT;
            }
        @endif

    </script>
    
    <script type="text/javascript">
        var ROOT = "{{url('')}}" , SYSTEM, tpinConfirm, otpConfirm, CALLBACK, OTPCALLBACK, TRANSTYPE="NONE", TPIN="";

        $(document).ready(function () {
            SYSTEM = {
                DEFAULT: function () {

                    SYSTEM.GETBALANCE();
                    
                    $( "#claimCommissionForm" ).validate({
                        rules: {
                            number: {
                                required: true
                            },
                            company: {
                                required: true
                            },
                            amount: {
                                required: true,
                                number : true
                            },
                        },
                        messages: {
                            number: {
                                required: "Please enter policy number",
                            },
                            company: {
                                required: "Please enter policy compnay",
                            },
                            amount: {
                                required: "Please enter amount",
                                number: "Amount should be numeric",
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
                            var form = $('#claimCommissionForm');
                            SYSTEM.FORMSUBMIT(form, function(data){
                                if (!data.statusText) {
                                    if(data.status == "TXN"){
                                        form[0].reset();
                                        $.alert({
                                            icon: 'fa fa-check',
                                            theme: 'modern',
                                            animation: 'scale',
                                            type: 'green',
                                            title   : "Success",
                                            content : data.message
                                        });
                                        form.closest(".modal").modal("hide");
                                    }else if(data.statuscode == "TXF"){
                                        $.alert({
                                            title: 'Oops!',
                                            content: data.message,
                                            type: 'red'
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
                },

                GETBALANCE: function () {
                    $.ajax({
                        url: "{{route('getbalance')}}",
                        type: "GET",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        success: function(result){
                            $.each(result, function (index, value) {
                                $('.'+index).text(value);
                            });
                        }
                    });
                },

                FORMBLOCK:function (form) {
                    form.block({
                        message: '<span class="text-semibold"><i class="icon-spinner4 spinner position-left"></i>&nbsp; Working on request</span>',
                        overlayCSS: {
                            backgroundColor: '#fff',
                            opacity: 0.8,
                            cursor: 'wait'
                        },
                        css: {
                            border: 0,
                            padding: '10px 15px',
                            color: '#fff',
                            width: 'auto',
                            '-webkit-border-radius': 2,
                            '-moz-border-radius': 2,
                            backgroundColor: '#333'
                        }
                    });
                },

                FORMUNBLOCK: function (form) {
                    form.unblock();
                },

                FORMSUBMIT: function(form, callback, block="none"){
                    form.ajaxSubmit({
                        dataType:'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        beforeSubmit:function(){
                            form.find('button[type="submit"]').button('loading');
                            if(block == "none"){
                                form.block({
                                    message: '<span class="text-semibold"><i class="icon-spinner4 spinner position-left"></i>&nbsp; Working on request</span>',
                                    overlayCSS: {
                                        backgroundColor: '#fff',
                                        opacity: 0.8,
                                        cursor: 'wait'
                                    },
                                    css: {
                                        border: 0,
                                        padding: '10px 15px',
                                        color: '#fff',
                                        width: 'auto',
                                        '-webkit-border-radius': 2,
                                        '-moz-border-radius': 2,
                                        backgroundColor: '#333'
                                    }
                                });
                            }
                        },
                        complete: function(){
                            form.find('button[type="submit"]').button('reset');
                            if(block == "none"){
                                form.unblock();
                            }
                        },
                        success:function(data){
                            callback(data);
                        },
                        error: function(errors) {
                            callback(errors);
                        }
                    });
                },

                AJAX: function(url, method, data, callback, loading="none", msg="Updating Data"){
                    $.ajax({
                        url: url,
                        type: method,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        dataType:'json',
                        data: data,
                        beforeSend:function(){
                            if(loading != "none"){
                                $(loading).block({
                                    message: '<span class="text-semibold"><i class="icon-spinner4 spinner position-left"></i> '+msg+'</span>',
                                    overlayCSS: {
                                        backgroundColor: '#fff',
                                        opacity: 0.8,
                                        cursor: 'wait'
                                    },
                                    css: {
                                        border: 0,
                                        padding: '10px 15px',
                                        color: '#fff',
                                        width: 'auto',
                                        '-webkit-border-radius': 2,
                                        '-moz-border-radius': 2,
                                        backgroundColor: '#333'
                                    }
                                });
                            }
                        },
                        complete: function () {
                            $(loading).unblock();
                        },
                        success:function(data){
                            callback(data);
                        },
                        error: function(errors) {
                            callback(errors);
                        }
                    });
                },

                SHOWERROR: function(errors, form){
                    if(errors.status == 422){
                        $.each(errors.responseJSON.errors, function (index, value) {
                            form.find('[name="'+index+'"]').closest('div.form-group').append('<p class="error">'+value+'</span>');
                        });
                        form.find('p.error').first().closest('.form-group').find('input').focus();
                        setTimeout(function () {
                            form.find('p.error').remove();
                        }, 5000);
                    }else if(errors.status == 400){
                        mdtoast.error("Oops! "+errors.responseJSON.message, { position: "top center" });
                    }else{
                        if(errors.message){
                            mdtoast.error("Oops! "+errors.message, { position: "top center" });
                        }else{
                            mdtoast.error("Oops! "+errors.statusText, { position: "top center" });
                        }
                    }
                },

                NOTIFY: function(msg, type="success",element="none"){
                    if(element == "none"){
                        switch(type){
                            case "success":
                                mdtoast.success("Success : "+msg, { position: "top center" });
                            break;

                            default:
                                mdtoast.error("Oops! "+msg, { position: "top center" });
                                break;
                        }
                    }else{
                        element.find('div.alert').remove();
                        element.prepend(`<div class="alert bg-`+type+` alert-styled-left">
                            <button type="button" class="close" data-dismiss="alert"><span>×</span><span class="sr-only">Close</span></button> `+msg+`
                        </div>`);

                        setTimeout(function(){
                            element.find('div.alert').remove();
                        }, 10000);
                    }
                },

                digitValidate: function (ele) {
                },

                tpinTabChange: function (myele, mycallback){
                    var ele = $(myele);
                    ele.val(ele.val().replace(/[^0-9]/g, ''));

                    if(ele.val() != ""){
                        ele.next().focus();
                    }else{
                        ele.prev().focus().val("");
                    }
                    var otp = "";
                    $.each($('.otp'),function(){
                        otp += $(this).val();
                    });

                    if(otp.length >= 6){
                        if (window.preventDuplicateKeyPresses)
                            return;

                        window.preventDuplicateKeyPresses = true;
                        tpinConfirm.close();
                        mycallback(otp);
                    }
                },

                tpinVerify: function (callback) {
                    CALLBACK = callback;
                    window.preventDuplicateKeyPresses = false;
                    @if(session("pincheck") == "yes")
                        tpinConfirm = $.confirm({
                            title: 'Verify T-Pin',
                            content: `<div class="form-group">
                                <label>Enter T-Pin</label><br>
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                                <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            </div>`,
                            buttons: {
                                'Submit': {
                                    btnClass: 'btn-primary',
                                    action: function () {
                                        var otp = "";
                                        $.each($('.otp'),function(){
                                            otp += $(this).val();
                                        });

                                        if(otp.length >= 6){
                                            if (window.preventDuplicateKeyPresses)
                                                return;

                                            window.preventDuplicateKeyPresses = true;
                                            CALLBACK(otp);
                                            return otp;
                                        }else{
                                            var ele = $(".otp");
                                            ele[0].focus();
                                            return false;
                                        }
                                    }
                                },
                                cancel: function () {
                                },

                                'Reset T-Pin': {
                                    btnClass: 'btn-danger',
                                    action: function () {
                                        window.open("{{url('member/profile/view')}}");
                                        return false;
                                    }
                                }
                            },
                            onContentReady: function () {
                                var ele = $(".otp");
                                ele[0].focus();

                                $(document).on('keyup', '.otp', function(event) {
                                    SYSTEM.tpinTabChange(this, function(otp){
                                        CALLBACK(otp);
                                    });
                                });
                            }
                        });
                    @else
                        var otp = "123456";
                        CALLBACK(otp);
                    @endif
                },

                otpTabChange: function (myele, mycallback){
                    var ele = $(myele);
                    ele.val(ele.val().replace(/[^0-9]/g, ''));

                    if(ele.val() != ""){
                        ele.next().focus();
                    }else{
                        ele.prev().focus().val("");
                    }
                    var otp = "";
                    $.each($('.otp'),function(){
                        otp += $(this).val();
                    });

                    if(otp.length >= 6){
                        if (window.preventDuplicateKeyPresses)
                            return;

                        window.preventDuplicateKeyPresses = true;
                        otpConfirm.close();
                        mycallback(otp);
                    }
                },

                otpVerify: function (otpcallback) {
                    CALLBACK = otpcallback;
                    window.preventDuplicateKeyPresses = false;
                    otpConfirm = $.confirm({
                        title: 'Otp Verification',
                        content: `<div class="form-group">
                            <label>Enter Otp</label><br>
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                            <input class="otp form-control" type="password" maxlength=1 placeholder="*">
                        </div>`,
                        buttons: {
                            'Submit': {
                                btnClass: 'btn-primary',
                                action: function () {
                                    var otp = "";
                                    $.each($('.otp'),function(){
                                        otp += $(this).val();
                                    });

                                    if(otp.length >= 6){
                                        if (window.preventDuplicateKeyPresses)
                                            return;

                                        window.preventDuplicateKeyPresses = true;
                                        CALLBACK(otp);
                                        return otp;
                                    }else{
                                        var ele = $(".otp");
                                        ele[0].focus();
                                        return false;
                                    }
                                }
                            },
                            cancel: function () {
                            },

                            'Resend Otp': {
                                btnClass: 'btn-danger',
                                action: function () {
                                }
                            }
                        },
                        onContentReady: function () {
                            var ele = $(".otp");
                            ele[0].focus();

                            $(document).on('keyup', '.otp', function(event) {
                                SYSTEM.otpTabChange(this, function(otp){
                                    CALLBACK(otp);
                                });
                            });
                        }
                    });
                }
            }

            SYSTEM.DEFAULT();
        });

        function complaint(id){
            $('#complaintModal').find('[name="transaction_id"]').val(id);
            $('#complaintModal').find('[name="product"]').val(transtype);
            $('#complaintModal').modal('show');
        }
    </script>

@stack('script')
</head>

<body class="navbar-top @yield('bodyClass')" @yield('bodyextra')>
    <input type="hidden" name="dataType" value="">

    @include('layouts.topbar')
    <div class="page-container">
        {{-- <div class="content-wrapper-before gradient-45deg-indigo-purple"></div> --}}
        <div class="page-content">
            @include('layouts.sidebar')

            <div class="content-wrapper">
                @include('layouts.pageheader')
                @yield('content')
            </div>
        </div>
    </div>
    <snackbar></snackbar>

    <div id="transactionModal" class="modal fade right" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Transaction Details</h6>
                </div>
                <div class="modal-body p-0">
                    <legend>Reference Details</legend>
                    <div class="form-group">
                        <label class="text-semibold">Reference No.</label>
                        <span class="pull-right-sm refno"></span>
                    </div>

                    <div class="form-group">
                        <label class="text-semibold">Tranaction Id</label>
                        <span class="pull-right-sm txnid"></span>
                    </div>

                    <div class="form-group">
                        <label class="text-semibold">Trasaction Date</label>
                        <span class="pull-right-sm created_at"></span>
                    </div>

                    <div class="form-group no-margin-bottom">
                        <label class="text-semibold">Transaction Status</label>
                        <span class="pull-right-sm status text-uppercase"></span>
                    </div>

                    <legend>Amount Details</legend>
                    <div class="amountData"></div>

                    <legend>Transaction Details</legend>
                    <div class="transactionData"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="complaintModal" class="modal fade" data-backdrop="false" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-slate">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h6 class="modal-title">Raise Complaint</h6>
                </div>
                <form id="complaintForm" action="{{route('helpsubmit')}}" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="transaction_id">
                        <input type="hidden" name="product">
                        <input type="hidden" name="subject" value="Fund Not Credited">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label>Bank Utr</label>
                            <input type="text" name="description" class="form-control" placeholder="Enter value" required="">
                        </div>

                        <div class="form-group">
                            <label>Screenshot</label>
                            <input type="file" name="screenshots" class="form-control" placeholder="Enter value" required="">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default btn-raised legitRipple" data-dismiss="modal" aria-hidden="true">Close</button>
                        <button class="btn bg-slate btn-raised legitRipple" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Updating">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
