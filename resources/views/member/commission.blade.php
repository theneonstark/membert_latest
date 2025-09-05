<div class="tabbable">
    <ul class="nav nav-tabs nav-tabs-bottom nav-justified no-margin">
        @foreach ($commission as $key => $value)
            <li class="{{($key == 'mobile') ? 'active' : ''}}"><a href="#{{$key}}" data-toggle="tab" class="legitRipple" aria-expanded="true">{{ucfirst($key)}}</a></li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($commission as $key => $value)
            <div class="tab-pane {{($key == 'mobile') ? 'active' : ''}}" id="{{$key}}">
                <table class="table table-bordered" cellspacing="0" style="width:100%">
                    <thead>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Value</th>
                    </thead>

                    <tbody>
                        @foreach ($value as $comm)
                            <tr>
                                <td>{{ucfirst($comm->provider->name)}}</td>
                                <td>{{ucfirst($comm->type)}}</td>
                                <td>{{ucfirst($comm->apiuser)}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>