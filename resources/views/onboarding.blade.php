@extends('layouts.app')
@section('title', "Complete Onboarding")
@section('pagetitle', 'Complete Onboarding')

@section('content')
    <div class="content">
        <div class="steps" accept-charset="UTF-8" enctype="multipart/form-data" novalidate="">
            <ul id="progressbar">
                <li class="active">Kyc Details</li>
                <li {{($user->step > 0 ) ? 'class=active' : ""}}>Kyc Documents</li>
                <li {{($user->step > 1 ) ? 'class=active' : ""}}>Bank Account Details</li>
                <li {{($user->step > 2 ) ? 'class=active' : ""}}>Firm Details</li>
            </ul>

            <fieldset {{($user->step == 0 ) ? "style=display:block;left:0%;opacity:1;" : "style=opacity:0;display:none;"}}>
                <h2 class="fs-title">Kyc Information</h2>
                <h3 class="fs-subtitle">We just need your kyc information to complete your profile</h3>

                <form class="onboardingForm" action="{{route('onboardingUpdate')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="step" value="1">
                    <div class="panel-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Pancard Number</label>
                                <input type="text" class="form-control" name="pancard" value="{{$user->pancard ?? ""}}" placeholder="Pancard Number" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Name on Pancard</label>
                                <input type="text" class="form-control" name="name" value="{{$user->name ?? ""}}" placeholder="Pancard Name" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Aadhar Number</label>
                                <input type="text" class="form-control" name="aadharcard" value="{{$user->aadharcard ?? ""}}" placeholder="Aadhar Number" required="">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea class="form-control" name="address" rows="3" required="">{{$user->address ?? ""}}</textarea>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>City</label>
                               <input type="text" class="form-control" name="city" value="{{$user->address ?? ""}}" placeholder="City" required="">
                            </div>
                            <div class="form-group col-md-4">
                                <label>State</label>
                               <input type="text" class="form-control" name="state" value="{{$user->state ?? ""}}" placeholder="State" required="">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Pincode</label>
                               <input type="text" class="form-control" name="pincode" value="{{$user->pincode ?? ""}}" placeholder="Pincode" required="">
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button class="btn btn-raised legitRipple pull-right next action-button" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Updating...">Submit</button>
                    </div>
                </form>
            </fieldset>

            <fieldset {{($user->step == 1) ? "style=display:block;left:0%;opacity:1;" : "style=opacity:0;display:none;"}}>
                <h2 class="fs-title">Documents Details</h2>
                <h3 class="fs-subtitle">We just need your kyc documents to complete your profile</h3>

                <form class="onboardingForm" action="{{route('onboardingUpdate')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="step" value="2">
                    <div class="panel-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Pancard Pic</label>
                                <input type="file" name="pancards" class="form-control" value="" placeholder="Enter Value" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Adhaarcard Front Photo</label>
                                <input type="file" name="aadharfronts" class="form-control" value="" placeholder="Enter Value" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Adhaarcard Back Photo</label>
                                <input type="file" name="aadharbacks" class="form-control" value="" placeholder="Enter Value" required="">
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button class="btn btn-raised legitRipple pull-left previous action-button" type="button">Previous</button>
                        <button class="btn btn-raised legitRipple pull-right next action-button" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Updating...">Submit</button>
                    </div>
                </form>
            </fieldset>

            <fieldset {{($user->step == 2 ) ? "style=display:block;left:0%;opacity:1;" : "style=opacity:0;display:none;"}}>
                <h2 class="fs-title">Bank Details</h2>
                <h3 class="fs-subtitle">We just need your company bank information to complete your profile</h3>

                <form class="onboardingForm" action="{{route('onboardingUpdate')}}" method="post">
                    {{ csrf_field() }}
                    <input type="hidden" name="step" value="3">
                    <div class="panel-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Bank Name</label>
                                <input type="text" class="form-control" name="bank" value="{{$userbank->bank ?? ""}}" placeholder="Bank Name" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Account Number</label>
                                <input type="text" class="form-control" name="account" value="{{$userbank->account ?? ""}}" placeholder="Account Number" required="">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Ifsc</label>
                                <input type="text" class="form-control" name="ifsc" value="{{$userbank->ifsc ?? ""}}" placeholder="Ifsc" required="">
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button class="btn btn-raised legitRipple pull-left previous action-button" type="button">Previous</button>
                        <button class="btn btn-raised legitRipple pull-right next action-button" type="submit" data-loading-text="<i class='fa fa-spin fa-spinner'></i> Updating...">Submit</button>
                    </div>
                </form>
            </fieldset>
        </div>
    </div>
@endsection

@push('style')
    <style type="text/css">
        .verifyTag{
            position: absolute;
            right: 12px;
            top: 25px;
            z-index : 99;
        }

        .verifyTag > a{
            padding: 7px 5px;
            font-size: 12px;
            border-radius: 3px;
        }

        .verifyTag > .text-success{
            background: #5cc9a7;
            color: white! important;
            padding: 7px;
            font-size: 12px !important;
            border-radius: 3px;
        }

        .steps fieldset {
            background: white;
            border: 0 none;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            -webkit-box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            padding: 20px 0px 0px 0px;
            border-top: 9px solid #D65328;
            box-sizing: border-box;
            width: 80%;
            margin: 0 10%;
        }

        .steps fieldset:not(:first-of-type) {
          display:none;
        }

        .error1{
           -moz-border-radius: 3px;
          -webkit-border-radius: 3px;
          border-radius: 3px;
          -moz-box-shadow: 0 0 0 transparent;
          -webkit-box-shadow: 0 0 0 transparent;
          box-shadow: 0 0 0 transparent;
          position: absolute;
          left: 525px;
          margin-top: -58px;
          padding: 0 10px;
          height: 39px;
          display: block;
          color: #ffffff;
          background: #e62163;
          border: 0;
          font: 14px Corbel, "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", "DejaVu Sans", "Bitstream Vera Sans", "Liberation Sans", Verdana, "Verdana Ref", sans-serif;
          line-height: 39px;
          white-space: nowrap;

        }

        .error1:before{
            width: 0;
          height: 0;
          left: -8px;
          top: 14px;
          content: '';
          position: absolute;
          border-top: 6px solid transparent;
          border-right: 8px solid #e62163;
          border-bottom: 6px solid transparent;
        }

        .error-log{
            margin: 5px 5px 5px 0;
          font-size: 19px;
          position: relative;
          bottom: -2px;
        }

        .question-log {
          margin: 5px 1px 5px 0;
          font-size: 15px;
          position: relative;
          bottom: -2px;
          }

        /*buttons*/
        .steps .action-button, .action-button {
          width: 100px !important;
          background: #2a2a2a;
          font-weight: bold;
          color: white;
          border-radius: 5px;
          cursor: pointer;
          padding: 10px 5px;
          margin: 10px auto;
          -webkit-transition: all 0.3s linear 0s;
          -moz-transition: all 0.3s linear 0s;
          -ms-transition: all 0.3s linear 0s;
          -o-transition: all 0.3s linear 0s;
          transition: all 0.3s linear 0s;
          display: block;
        }

        .steps .next, .steps .submit{
            float: right;
        }

        .steps .previous{
          float:left;
        }

        .steps .explanation{
        display: block;
          clear: both;
          width: 540px;
          background: #f2f2f2;
          position: relative;
          margin-left: -30px;
          padding: 22px 0px;
          margin-bottom: -10px;
          border-bottom-left-radius: 3px;
          border-bottom-right-radius: 3px;
          top: 10px;
          text-align: center;
          color: #333333;
          font-size: 12px;
          font-weight: 200;
          cursor:pointer;
        }


        /*headings*/
        .fs-title {
            margin: 0 0 5px;
            line-height: 1;
            color: #0c4370;
            font-size: 18px;
            font-weight: 500;
            text-align:center;
        }

        .fs-subtitle {
            font-weight: normal;
            font-size: 15px;
            color: #2a2a2a;
            margin: 15px;
            text-align: center;
        }

        /*progressbar*/
        #progressbar {
          margin-bottom: 30px;
          overflow: hidden;
          /*CSS counters to number the steps*/
          counter-reset: step;
          width:100%;
          text-align: center;
          padding: 0px;
        }
        #progressbar li {
          list-style-type: none;
            color: rgb(51, 51, 51);
          font-weight: 500;
          width: 25%;
          float: left;
          position: relative;
        }

        #progressbar li:before {
          content: counter(step);
          counter-increment: step;
          width: 30px;
          line-height: 30px;
          display: block;
          color: #333;
          background: white;
          border-radius: 3px;
          margin: 0 auto 5px auto;
        }
        /*progressbar connectors*/
        #progressbar li:after {
          content: '';
          width: 100%;
          height: 2px;
          background: white;
          position: absolute;
          left: -50%;
          top: 9px;
          z-index: -1; /*put it behind the numbers*/
        }
        #progressbar li:first-child:after {
          /*connector not needed before the first step*/
          content: none; 
        }
        /*marking active/completed steps green*/
        /*The number of the step and the connector before it = green*/
        #progressbar li.active:before,  #progressbar li.active:after{
          background: #0c4370;
          color: white;
        }


        /* my modal */

        .modal p{
          font-size: 15px;
          font-weight: 100;
          font-family: sans-serif;
          color: #3C3B3B;
          line-height: 21px;
        }

        .modal {
          position: fixed;
          top: 50%;
          left: 50%;
          width: 50%;
          max-width: 630px;
          min-width: 320px;
          height: auto;
          z-index: 2000;
          visibility: hidden;
          -moz-backface-visibility: hidden;
          -webkit-backface-visibility: hidden;
          backface-visibility: hidden;
          -moz-transform: translate(-50%, -50%);
          -ms-transform: translate(-50%, -50%);
          -webkit-transform: translate(-50%, -50%);
          transform: translate(-50%, -50%);
        }
        .modal.modal-show {
          visibility: visible;
        }
        .lt-ie9 .modal {
          top: 0;
          margin-left: -315px;
        }

        .modal-content {
          background: #ffffff;
          position: relative;
          margin: 0 auto;
          padding: 40px;
          border-radius: 3px;
        }

        .modal-overlay {
          background: #000000;
          position: fixed;
          visibility: hidden;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          z-index: 1000;
          filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=0);
          opacity: 0;
          -moz-transition-property: visibility, opacity;
          -o-transition-property: visibility, opacity;
          -webkit-transition-property: visibility, opacity;
          transition-property: visibility, opacity;
          -moz-transition-delay: 0.5s, 0.1s;
          -o-transition-delay: 0.5s, 0.1s;
          -webkit-transition-delay: 0.5s, 0.1s;
          transition-delay: 0.5s, 0.1s;
          -moz-transition-duration: 0, 0.5s;
          -o-transition-duration: 0, 0.5s;
          -webkit-transition-duration: 0, 0.5s;
          transition-duration: 0, 0.5s;
        }
        .modal-show .modal-overlay {
          visibility: visible;
          filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=60);
          opacity: 0.6;
          -moz-transition: opacity 0.5s;
          -o-transition: opacity 0.5s;
          -webkit-transition: opacity 0.5s;
          transition: opacity 0.5s;
        }

        /*slide*/
        .modal[data-modal-effect|=slide] .modal-content {
          filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=0);
          opacity: 0;
          -moz-transition: all 0.5s 0;
          -o-transition: all 0.5s 0;
          -webkit-transition: all 0.5s 0;
          transition: all 0.5s 0;
        }
        .modal[data-modal-effect|=slide].modal-show .modal-content {
          filter: progid:DXImageTransform.Microsoft.Alpha(enabled=false);
          opacity: 1;
          -moz-transition: all 0.5s 0.1s;
          -o-transition: all 0.5s 0.1s;
          -webkit-transition: all 0.5s;
          -webkit-transition-delay: 0.1s;
          transition: all 0.5s 0.1s;
        }
        .modal[data-modal-effect=slide-top] .modal-content {
          -moz-transform: translateY(-300%);
          -ms-transform: translateY(-300%);
          -webkit-transform: translateY(-300%);
          transform: translateY(-300%);
        }
        .modal[data-modal-effect=slide-top].modal-show .modal-content {
          -moz-transform: translateY(0);
          -ms-transform: translateY(0);
          -webkit-transform: translateY(0);
          transform: translateY(0);
        }


        /* RESPONSIVE */

        /* moves error logs in tablet/smaller screens */

        @media (max-width:1000px){

        /*brings inputs down in size */
        .steps input, .steps textarea {
          outline: none;
          display: block;
          width: 100% !important;
          }

          /*brings errors in */
          .error1 {
          left: 345px;
          margin-top: -58px;
        }
        }

        @media (max-width:675px){
        /*mobile phone: uncollapse all fields: remove progress bar*/

        .steps {
          width: 100%;
          margin: 50px auto;
          position: relative;
        }

        /*move error logs */
        .error1 {
          position: relative;
          left: 0 !important;
          margin-top: 24px;
          top: -11px;
        }

        .error1:before {
          width: 0;
          height: 0;
          left: 14px;
          top: -14px;
          content: '';
          position: absolute;
          border-left: 6px solid transparent;
          border-bottom: 8px solid #e62163;
          border-right: 6px solid transparent;
          }

        /*show hidden fieldsets */
        .steps fieldset:not(:first-of-type) {
          display: block;
        }

        .steps fieldset{
          position:relative;
          width: 100%;
          margin:0 auto;
          margin-top: 45px;
        }

        .steps .explanation{
          display:none;
        }

        .steps .submit {
          float: right;
          margin: 28px auto 10px;
          width: 100% !important;
        }

        }
        /* Info */
        .info {
          width: 300px;
          margin: 35px auto;
          text-align: center;
          font-family: 'roboto', sans-serif;
        }

        .info h1 {
          margin: 0;
          padding: 0;
          font-size: 28px;
          font-weight: 400;
          color: #333333;
          padding-bottom: 5px;

        }
        .info span {
          color:#666666;
          font-size: 13px;
          margin-top:20px;
        }
        .info span a {
          color: #666666;
          text-decoration: none;
        }
        .info span .fa {
          color: rgb(226, 168, 16);
          font-size: 19px;
          position: relative;
          left: -2px;
        }

        .info span .spoilers {
          color: #999999;
          margin-top: 5px;
          font-size: 10px;
        }
    </style>
@endpush

@push('script')
    <script src="{{asset('')}}assets/js/core/jquery.easing.min.js"></script>
    <script type="text/javascript" src="{{asset('')}}assets/js/plugins/forms/styling/uniform.min.js"></script>
    <script type="text/javascript">
        var USERSYSTEM;
        $(document).ready(function() {
            var current_fs, next_fs, previous_fs;
            var left, opacity, scale;
            var animating;

            @if(isset($business->firm_type) && $business->firm_type != "")
                $("[name='firm_type']").val("{{$business->firm_type}}").trigger("change");
                $("[value='{{$business->firm_pan_available}}']").trigger("click");
            @endif

            $(".styled").uniform({
                radioClass: 'choice'
            });

            $(window).load(function(){
                $("[name='business_type']").val("{{$business->business_type ?? ""}}");
                $("[name='business_industry']").val("{{$business->business_industry ?? ""}}");
            });

            $(".previous").click(function() {
                if (animating) return false;
                animating   = true;
                current_fs  = $(this).parent().parent().parent();
                previous_fs = $(this).parent().parent().parent().prev();
                $("#progressbar li").eq($("fieldset").index(current_fs)).removeClass("active");
                previous_fs.show();
                current_fs.animate({
                    opacity: 0
                }, {
                    step: function(now, mx) {
                        scale = 0.8 + (1 - now) * 0.2;
                        left = ((1 - now) * 50) + "%";
                        opacity = 1 - now;
                        current_fs.css({
                            'left': left
                        });
                        previous_fs.css({
                            'transform': 'scale(' + scale + ')',
                            'opacity': opacity
                        });
                    },
                    duration: 800,
                    complete: function() {
                        current_fs.hide();
                        animating = false;
                    },
                    easing: 'easeInOutExpo'
                });
            });

            $('.onboardingForm').submit(function(event) {
                var form = $(this);
                var step = form.find("[name='step']").val();
                
                SYSTEM.FORMSUBMIT(form, function(data){
                    if (!data.statusText) {
                        if(data.status == "TXN"){
                            if(step == "3"){
                                swal({
                                    type: 'success',
                                    title : 'Done',
                                    text: data.message,
                                    showConfirmButton: false,
                                    timer: 3000,
                                    onClose: () => {
                                        window.location.reload();
                                    }
                                });
                            }else{
                                if (animating) return false;
                                animating  = true;
                                current_fs = form.parent();
                                next_fs    = form.parent().next();
                                $("#progressbar li").eq($("fieldset").index(next_fs)).addClass("active");

                                next_fs.show();
                                current_fs.animate({
                                    opacity: 0
                                }, {
                                    step: function(now, mx) {
                                        scale   = 1 - (1 - now) * 0.2;
                                        left    = (now * 50) + "%";
                                        opacity = 1 - now;
                                        current_fs.css({
                                            'transform': 'scale(' + scale + ')'
                                        });
                                        next_fs.css({
                                            'left': left,
                                            'opacity': opacity
                                        });
                                    },
                                    duration: 800,
                                    complete: function() {
                                        current_fs.hide();
                                        animating = false;
                                    },
                                    easing: 'easeInOutExpo'
                                });
                            }
                        }else{
                            SYSTEM.NOTIFY(data.message, "error");
                        }
                    } else {
                        SYSTEM.SHOWERROR(data, form);
                    }
                });
                return false;
            });
        });
    </script>
@endpush
