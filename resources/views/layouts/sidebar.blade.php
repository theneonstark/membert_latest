<div class="sidebar sidebar-main sidebar-default sidebar-fixed" style="background: #fff; width: 270px; height: 100vh; border-right: 1px solid #e0e0e0; border-radius: 0 5px 5px 0;">
    <div class="sidebar-content">
        <div class="sidebar-category sidebar-category-visible">
            <div class="category-content no-padding">
                <ul class="navigation navigation-main navigation-accordion" style="list-style: none; padding: 0;">
                    <!-- Dashboard -->
                    <li class="legitRipple">
                        <a href="{{ route('home') }}" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none; background: {{ request()->routeIs('home') ? 'linear-gradient(90deg, #e0f7fa, #b2ebf2)' : 'none' }};">
                            <i class="icon-grid" style="font-size: 2.2rem; margin-right: 15px; color: {{ request()->routeIs('home') ? '#26a69a' : '#666' }};"></i>
                            <span style="font-size: 1.7rem;">Dashboard</span>
                        </a>
                    </li>

                    <!-- Panel Resource -->
                    @if (Myhelper::can(['view_apiuser', 'change_company_profile']))
                        <li class="legitRipple">
                            <a href="javascript:void(0)" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none;">
                                <i class="icon-stack" style="font-size: 2.2rem; margin-right: 15px; color: #666;"></i>
                                <span style="font-size: 1.7rem;">Panel Resource</span>
                                <i class="icon-chevron-right" style="margin-left: auto; font-size: 1.5rem; color: #bbb;"></i>
                            </a>
                            <ul style="list-style: none; padding: 0 0 0 5px; background: #f9f9f9;">
                                @if (Myhelper::can('view_apiuser'))
                                    <li class="legitRipple">
                                        <a href="{{ route('member', ['type' => 'apiuser']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                            <span style="font-size: 1.5rem;">Api User</span>
                                        </a>
                                    </li>
                                @endif
                                @if (Myhelper::can('change_company_profile'))
                                    <li class="legitRipple">
                                        <a href="{{ route('resource', ['type' => 'companyprofile']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                            <span style="font-size: 1.5rem;">Company Profile</span>
                                        </a>
                                    </li>
                                @endif
                                @if (Myhelper::can('scheme_manager'))
                                    <li class="legitRipple">
                                        <a href="{{ route('resource', ['type' => 'scheme']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                            <span style="font-size: 1.5rem;">Scheme Manager</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    <!-- Payment Resource -->
                    <li class="legitRipple">
                        <a href="javascript:void(0)" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                            <i class="icon-wallet" style="font-size: 2.2rem; margin-right: 15px; color: #666;"></i>
                            <span style="font-size: 1.7rem;">Services</span>
                        </a>
                        <ul style="list-style: none; padding: 0 0 0 2px; background: #f9f9f9;">
                            @if (Myhelper::can('fund_request'))
                                <li class="legitRipple">
                                    <a href="{{ route('fund', ['type' => 'qrcode']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                        <span style="font-size: 1.5rem;">Payin</span>
                                    </a>
                                </li>
                                <!--<li class="legitRipple">-->
                                <!--    <a href="{{ route('fund', ['type' => 'upirequest']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">-->
                                <!--        <span style="font-size: 1.5rem;">Upi Add Money</span>-->
                                <!--    </a>-->
                                <!--</li>-->
                                <li class="legitRipple">
                                    <a href="{{ route('fund', ['type' => 'addmoney']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                        <span style="font-size: 1.5rem;">Wallet TopUp</span>
                                    </a>
                                </li>
                            @endif
                            @if (Myhelper::can('my_payout'))
                                <li class="legitRipple">
                                    <a href="{{ route('payout', ['type' => 'payout', 'action' => 'initiate']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                        <span style="font-size: 1.5rem;">Settlement Request</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                    <!-- Reports -->
                    <li class="legitRipple">
                        <a href="javascript:void(0)" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                            <i class="icon-stats-bars" style="font-size: 2.2rem; margin-right: 15px; color: #666;"></i>
                            <span style="font-size: 1.7rem;">Reports</span>
                        </a>
                        <ul style="list-style: none; padding: 0 0 0 5px; background: #f9f9f9;">
                            <li class="legitRipple">
                                <a href="{{ route('reports') }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                    <span style="font-size: 1.5rem;">Transaction</span>
                                </a>
                            </li>
                            <li class="legitRipple">
                                <a href="{{ route('reports', ['type' => 'ladger']) }}" style="display: flex; align-items: center; padding: 10px 20px; color: #333; text-decoration: none; border-radius: 5px;">
                                    <span style="font-size: 1.5rem;">Ledger</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Support -->
                    <li class="legitRipple">
                        <a href="{{ route('help', ['type' => 'complaint']) }}" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none; background: {{ request()->routeIs('help', ['type' => 'complaint']) ? 'linear-gradient(90deg, #e0f7fa, #b2ebf2)' : 'none' }}; border-radius: 5px;">
                            <i class="icon-lifebuoy" style="font-size: 2.2rem; margin-right: 15px; color: #666;"></i>
                            <span style="font-size: 1.7rem;">Support</span>
                        </a>
                    </li>

                    <!-- API Tools -->
                    <li class="legitRipple">
                        <a href="{{ route('apisetup', ['type' => 'cerdentials']) }}" style="display: flex; align-items: center; padding: 15px 20px; color: #333; text-decoration: none; background: {{ request()->routeIs('apisetup', ['type' => 'cerdentials']) ? 'linear-gradient(90deg, #e0f7fa, #b2ebf2)' : 'none' }}; border-radius: 5px;">
                            <i class="icon-cog" style="font-size: 2.2rem; margin-right: 15px; color: #666;"></i>
                            <span style="font-size: 1.7rem;">Api Tools</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Optional CSS for hover effects and responsiveness -->
<style>
    .sidebar-main .navigation > li > a:hover {
        background: linear-gradient(90deg, #e0f7fa, #b2ebf2);
        color: #000 !important;
    }

    .sidebar-main .navigation > li > a:hover i {
        color: #26a69a;
    }

    .sidebar-main .navigation > li > ul > li > a:hover {
        color: #26a69a;
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .sidebar-main {
            width: 100%;
            height: auto;
            position: relative;
        }
    }
</style>