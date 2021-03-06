<header class="topnavbar-wrapper">
    <!-- START Top Navbar-->
    <nav role="navigation" class="navbar topnavbar">
        <!-- START navbar header-->
        <div class="navbar-header">
            <a href="/" class="navbar-brand">
                <div class="brand-logo">
                    <img src="{{ asset('img/logo.png') }}" alt="App Logo" class="img-responsive">
                </div>
                <div class="brand-logo-collapsed">
                    <img src="{{ asset('img/logo-single.png') }}" alt="App Logo" class="img-responsive">
                </div>
            </a>
        </div>
        <!-- END navbar header-->
        <!-- START Nav wrapper-->
        <div class="nav-wrapper">
            <!-- START Left navbar-->
            <ul class="nav navbar-nav">
                <li>
                    <!-- Button used to collapse the left sidebar. Only visible on tablet and desktops-->
                    <a href="#" data-toggle-state="aside-collapsed" class="hidden-xs" title="Collapse/expand sidebar">
                        <em class="fa fa-navicon"></em>
                    </a>
                    <!-- Button to show/hide the sidebar on mobile. Visible on mobile only.-->
                    <a href="#" data-toggle-state="aside-toggled" data-no-persist="true" class="visible-xs sidebar-toggle">
                        <em class="fa fa-navicon"></em>
                    </a>
                </li>
                <!-- START User avatar toggle-->
                <li>
                    <!-- Button used to collapse the left sidebar. Only visible on tablet and desktops-->
                    <a id="user-block-toggle" href="#user-block" data-toggle="collapse" title="Show/hide user block">
                        <em class="icon-user"></em>
                    </a>
                </li>
                <!-- END User avatar toggle-->
                <!-- START lock screen-->
                <li>
                    <a href="{{ url('auth/logout') }}" title="Log out">
                        <em class="icon-logout"></em>
                    </a>
                </li>
                <!-- END lock screen-->
            </ul>
            @if(Auth::user()->isDeveloper())
                <ul class="nav navbar-nav navbar-right">
                    <!-- Search icon-->
                    <li>
                        <a href="javascript:void(0)">Balance:
                        <?php
                            $balance = Cache::get('balance_'.Auth::user()->id);
                            if ($balance === null)
                                $balance = Auth::user()->getClientBalance();
                            echo $balance.'$';
                        ?>
                        </a>
                    </li>
                    <!-- Fullscreen (only desktops)-->
                    <!-- END Offsidebar menu-->
                </ul>
                <!-- END Right Navbar-->
            @endif
        </div>
    </nav>
    <!-- END Top Navbar-->
</header>