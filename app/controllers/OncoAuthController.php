<?php
use Jacopo\Authentication\Controllers;

class OncoAuthController extends AuthController {

   public function postClientLogin()
   {
      list($email, $password, $remember) = $this->getLoginInput();

      try
      {
         $this->authenticator->authenticate(
            array(
               "email" => $email,
               "password" => $password
            ), 
            $remember
         );
      }
      catch(JacopoExceptionsInterface $e)
      {
         $errors = $this->authenticator->getErrors();
         return Redirect::action('Jacopo\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
      }

      return Redirect::to(Config::get('laravel-authentication-acl::config.user_login_redirect_url'));
   }

   /**
   * @return array
   */
   private function getLoginInput()
   {
      $email    = Input::get('email');
      $password = Input::get('password');
      $remember = Input::get('remember');
      return array($email, $password, $remember);
   }
}
