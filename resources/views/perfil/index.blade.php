
<div class="dropdown main-profile-menu">
	<a class="d-flex" href="">
		<span class="main-img-user" ><img alt="avatar" src="{{  url('spruha/img/users/user.png')}}"></span>
	</a>
	<div class="dropdown-menu">
		<div class="header-navheading">
			<h6 class="main-notification-title"></h6>
			
		</div>
		<a class="dropdown-item border-top" href="#">
			<i class="fe fe-user"></i> My Profile
		</a>
		<a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('submit-form').submit();">
			<i class="fe fe-power"></i> Cerrar sesión
		</a>
		<form id="submit-form" action="{{ route('logout') }}" method="POST" class="hidden">
    		@csrf
    		@method('POST')
    	</form>
	</div>
</div>




