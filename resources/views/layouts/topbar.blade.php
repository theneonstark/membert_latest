<div class="navbar navbar-inverse navbar-fixed-top" style="border-radius: 0 0 25px 25px;">
    <div class="navbar-header">
        <a class="navbar-brand" href="{{route('home')}}">{{session("companyname") ?? ""}}</a>

        <ul class="nav navbar-nav pull-right visible-xs-block">
            <li><a data-toggle="collapse" data-target="#navbar-mobile" class="legitRipple"><i class="icon-tree5"></i></a></li>
        </ul>
    </div>

    <div class="navbar-collapse collapse" id="navbar-mobile">
        <ul class="nav navbar-nav">
        </ul>

        <div class="navbar-right">
            <ul class="nav navbar-nav">
                
                <li class="dropdown dropdown-user">
                    <a class="dropdown-toggle p-r-0 legitRipple" data-toggle="dropdown" aria-expanded="false">
                        <span>{{ explode(' ',ucwords(Auth::user()->name))[0] }} (AID - {{Auth::id()}})</span>
                        <i class="caret"></i>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-right" style="margin-top:6px">
                        <li class="dropdown-header">
                            Agent Id : {{ Auth::id()}}
                        </li>
                        <li class="dropdown-header">
                            Name : {{ Auth::user()->name}}
                        </li>
                        <li class="dropdown-header">
                            Member : {{Auth::user()->role->name}}
                        </li>
                        <li class="divider"></li>
                        <li><a href="{{route('profile')}}"><i class="icon-user-plus"></i> <span>Kyc Details</span></a></li>
                        <li><a href="{{route('resource', ['type' => 'commission'])}}"><i class="icon-coins"></i> <span>Service Charges </span></a></li>
                        <li><a href="{{route('logout')}}"><i class="icon-switch2"></i> <span>Logout</span></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>