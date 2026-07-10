<?php

test('the application renders with an RTL, Arabic document shell', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('dir="rtl"', false);
    $response->assertSee('lang="ar"', false);
});
