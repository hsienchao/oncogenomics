<?php namespace Onco\Providers;

use Illuminate\Support\ServiceProvider;

class IntendedUrlServiceProvider extends ServiceProvider {
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        // Check to see if we were redirected to this page with the Redirect::intended().
        //        We extended the class to track when the redirect occurs so we know to reload additional request data
        if (\Session::has('intended.load')) {
            // intended.load could be set without these being set if we were redirected to the default page
            //        if either exists, both should exist but checking separately to be safe
            if (\Session::has('intended.method')) {
                \Request::setMethod(\Session::get('intended.method'));
            }
            if (\Session::has('intended.input')) {
                \Request::replace(\Session::get('intended.input'));
            }
            // Erase all session keys created to track the intended request
            \Session::forget('intended');
        }
    }

    public function register() {
    }
}
