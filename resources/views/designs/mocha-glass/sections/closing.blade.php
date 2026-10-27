<div class="mg-closing inv-animate">
    @if (filled($config['message']))
        <p>{{ $config['message'] }}</p>
    @endif
    <div class="mg-rule"></div>
    <p>{{ $config['heading'] }}@if (filled($config['signature']))<br>{{ $config['signature'] }}@endif</p>
    @if (! empty($config['inviters']))
        <p class="mg-closing__inviters">
            {{ __('Also inviting') }}:
            @foreach ($config['inviters'] as $inviter)
                <br>{{ $inviter }}
            @endforeach
        </p>
    @endif
</div>
