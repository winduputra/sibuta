<!-- Sidebar -->
<div class="sidebar">

  <!-- Sidebar Menu -->
  <nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

      <li class="nav-item">
          <a href="{{ route('user.list') }}" class="nav-link {{ (strpos(Route::currentRouteName(), 'user') === 0) ? 'active' : '' }}">
              <i class="nav-icon fa fa-users"></i>
              <p>
              User
              </p>
          </a>
      </li>

      <li class="nav-item">
          <a href="{{ route('report.form') }}" class="nav-link {{ (strpos(Route::currentRouteName(), 'report') === 0 AND strpos(Route::currentRouteName(), 'review') === false) ? 'active' : '' }}">
              <i class="nav-icon fa fa-copy"></i>
              <p>
              Report
              </p>
          </a>
      </li>

      <li class="nav-item">
          <a href="{{ route('report.review.form') }}" class="nav-link {{ (strpos(Route::currentRouteName(), 'review') !== false) ? 'active' : '' }}">
              <i class="nav-icon fa fa-copy"></i>
              <p>
              Report Survey
              </p>
          </a>
      </li>

      {{-- <li class="nav-item">
          <a href="{{ route('setting.list') }}" class="nav-link {{ (strpos(Route::currentRouteName(), 'setting') === 0) ? 'active' : '' }}">
              <i class="nav-icon fa fa-cogs"></i>
              <p>
              Pengaturan
              </p>
          </a>
      </li> --}}
              
    </ul>
  </nav>
  <!-- /.sidebar-menu -->
</div>
<!-- /.sidebar -->