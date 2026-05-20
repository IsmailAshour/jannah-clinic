<?php

it('guest hitting / sees the public home (P5b)', function () {
    $this->get('/')->assertOk();
});
