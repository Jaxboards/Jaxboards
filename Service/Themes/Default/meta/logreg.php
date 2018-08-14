<?php

$meta = array('login-form' => '<div class="box login">
 <div class="title">Login</div>
 <div class="content">
  <form action="?act=logreg3" method="post">
   <label for="user">Username:</label><input type="text" id="user" name="user" /><br />
   <label for="pass">Password:</label><input type="password" id="pass" name="pass" /><br />
   <input type="submit" value="Submit" />
  </form>
 </div>
</div>',

    'register-form' => '<div class="box register">
 <div class="title">Registration</div>
 <div class="content">
  <form method="post" onsubmit="return RUN.submitForm(this)">
   <input type="hidden" name="act" value="logreg1">
   <input type="hidden" name="register" value="true" />
   <div style="display:none"><input type="text" id="username" /></div>
   <label for="name">Username:</label><input type="text" name="name" id="name" onkeyup="this.form.display_name.value=this.value" /><br />
   <label for="display_name">Display Name:</label><input type="text" name="display_name" id="display_name" /><br />
   <label for="pass1">Password:</label><input type="password" name="pass1" id="pass1" /><br />
   <label for="pass2">Confirm:</label><input type="password" name="pass2" id="pass2" /><br />
   <label for="email">Email:</label><input type="text" name="email" id="email" /><br />
   %s
   <input type="submit" value="Register" />
  </form>
 </div>
</div>',

    'forgot-password-form' => '<div class="box login">
 <div class="title">Forgot Password</div>
 <div class="content">
  <form action="?act=logreg6" method="post" onsubmit="return RUN.submitForm(this)">
   <label for="user">Username:</label><input type="text" id="user" name="user" /><br />
   %s
   <input type="submit" value="Submit" />
  </form>
 </div>
</div>',

    'forgot-password2-form' => '<div class="box login">
 <div class="title">Generate a New Password</div>
 <div class="content">
  <form action="?act=logreg6" method="post" onsubmit="return RUN.submitForm(this)">
   <label for="pass1">Password:</label><input type="password" id="pass1" name="pass1" /><br />
   <label for="pass2">Confirm:</label><input type="password" id="pass2" name="pass2" /><br />
   %s
   <input type="submit" value="Submit" />
  </form>
 </div>
</div>',
);
