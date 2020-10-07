<ul class="nav nav-sidebar">
@if(isset($sidebar_items) && $sidebar_items)
@foreach($sidebar_items as $name => $data)
	@if ($name != "Add user")
    	<li class="{{Jacopo\Library\Views\Helper::get_active($data['url'])}}"><a href="{{$data['url']}}">{{$data['icon']}} {{$name}}</a></li>
    @endif
@endforeach
@endif
</ul>
