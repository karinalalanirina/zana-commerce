@include('errors.layout', [
    'title' => 'Down for maintenance',
    'eyebrow' => 'Be right back',
    'code' => '503',
    'headline' =>
        brand_name() . ' is briefly offline.',
    'body' =>
        "We're shipping an update or running scheduled maintenance. The app will be back in a few minutes / no action needed on your end.",
])
