<?php namespace Onco\Providers;

use Illuminate\Routing\Redirector;
use Illuminate\Support\ServiceProvider;
class RedirectServiceProvider extends ServiceProvider {

    protected $defer = true;

    /**
     * Register the Redirector service.
     *
     * ** Copy of class registerRedirector from RoutingServiceProvider,
     * using a different "use" statement at the top to use the extended Redirector class
     * Extending the RoutingServiceProvider was more of a pain to do right since it is loaded as a base provider in the Application
     *
     * @return void
     */
    public function register()
    {
        $this->app['redirect'] = $this->app->share(function($app)
        {
            $redirector = new Redirector($app['url']);

            // If the session is set on the application instance, we'll inject it into
            // the redirector instance. This allows the redirect responses to allow
            // for the quite convenient "with" methods that flash to the session.
            if (isset($app['session.store']))
            {
                $redirector->setSession($app['session.store']);
            }

            return $redirector;
        });
    }
    public function provides() {
        return array('redirect');
    }
}
