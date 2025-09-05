<div class="row">
    <div class="col-md-12">
        <form id="searchForm">
            <input type="hidden" name="type">
            <div class="panel panel-default">
                <div class="panel-heading" style="background: #008080; display: flex; align-items: center; justify-content: space-between; padding: 10px;">
    <h4 class="panel-title" style="margin: 0; color: #fff;">Search</h4>
    <div class="heading-elements" style="display: flex; gap: 8px;">
        <!-- Search Button -->
        <button type="submit" class="btn bg-slate btn-xs btn-labeled legitRipple btn-lg" 
            data-loading-text="<b><i class='fa fa-spin fa-spinner'></i></b> Searching">
            <b><i class="icon-search4"></i></b> Search
        </button>

        <!-- Refresh Button -->
        <button type="button" class="btn btn-warning btn-xs btn-labeled legitRipple" id="formReset" 
            data-loading-text="<b><i class='fa fa-spin fa-spinner'></i></b> Refreshing">
            <b><i class="icon-rotate-ccw3"></i></b> Refresh
        </button>

        <!-- Export Button -->
        @if (!empty($export))
            <button type="button" class="btn btn-primary btn-xs btn-labeled legitRipple" 
                product="{{ $export }}" id="reportExport">
                <b><i class="icon-cloud-download2"></i></b> Export
            </button>
        @endif

        <!-- New Export Button -->
        @if (!empty($newexport))
            <button type="button" class="btn btn-primary btn-xs btn-labeled legitRipple" 
                product="{{ $newexport }}" id="newreportExport">
                <b><i class="icon-cloud-download2"></i></b> Export
            </button>
        @endif
    </div>
</div>

                <div class="panel-body p-tb-10">
                    @if(isset($mystatus))
                        <input type="hidden" name="status" value="{{$mystatus}}">
                    @endif
                    <div class="row">
                        <div class="form-group col-md-2 m-b-10">
                            <input type="text" name="from_date" class="form-control mydate" placeholder="From Date">
                        </div>
                        <div class="form-group col-md-2 m-b-10">
                            <input type="text" name="to_date" class="form-control mydate" placeholder="To Date">
                        </div>
                        <div class="form-group col-md-2 m-b-10">
                            <input type="text" name="searchtext" class="form-control" placeholder="Search Value">
                        </div>
                        @if (Myhelper::hasNotRole(['retailer', 'apiuser']))
                            <div class="form-group col-md-2 m-b-10 {{ isset($agentfilter) ? $agentfilter : ''}}">
                                <input type="text" name="agent" class="form-control" placeholder="Agent Id / Parent Id">
                            </div>
                        @endif

                        @if(isset($status))
                        <div class="form-group col-md-2">
                            <select name="status" class="form-control select">
                                <option value="">Select {{$status['type'] ?? ''}} Status</option>
                                @if (isset($status['data']) && sizeOf($status['data']) > 0)
                                    @foreach ($status['data'] as $key => $value)
                                        <option value="{{$key}}">{{$value}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        @endif

                        @if(isset($product))
                        <div class="form-group col-md-2">
                            <select name="product" class="form-control select">
                                <option value="">Select {{$product['type'] ?? ''}}</option>
                                @if (isset($product['data']) && sizeOf($product['data']) > 0)
                                    @foreach ($product['data'] as $key => $value)
                                        <option value="{{$key}}">{{$value}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>