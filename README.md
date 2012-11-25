# Soopfw module: OpenID

The module will provide an OpenID LoginHandler for the [SoopFw framework](http://soopfw.org).

# What is OpenID?
OpenID allows you to use an existing account to sign in to multiple websites, without needing to create new passwords.

You may choose to associate information with your OpenID that can be shared with the websites you visit, such as a name or email address. With OpenID, you control how much of that information is shared with the websites you visit.

With OpenID, your password is only given to your identity provider, and that provider then confirms your identity to the websites you visit.  Other than your provider, no website ever sees your password, so you don’t need to worry about an unscrupulous or insecure website compromising your identity.

OpenID is rapidly gaining adoption on the web, with over one billion OpenID enabled user accounts and over 50,000 websites accepting OpenID for logins.  Several large organizations either issue or accept OpenIDs, including Google, Facebook, Yahoo!, Microsoft, AOL, MySpace, Sears, Universal Music Group, France Telecom, Novell, Sun, Telecom Italia, and many more.

Source: http://openid.net/get-an-openid/what-is-openid

# Features
 - The main feature is to **provide** a **LoginHandler** for **any** OpenID identity provider which use **OpenID 1.0**, **OpenID 1.1** or **OpenID 2.0**.
 - If you only want to **trust specified providers**, you can enable **client-verification** and **upload a file** or **choose a folder** with files containing the **accepted CA's**.
 - By default the data from the OpenID provider will **synchronized** only when the user did **not currently exist** and the new account will be **created**, but you can **configurate** that the
data will **always synchronize** everytime the user log in.

# How to use
First of all download this module and install the source files under **{docroot}/modules/open_id**.
After that you need to enable the module.

Login with the admin account or use the **clifs** command to enable/install the module.

# Login handler
Now you need to be really logged in with the admin account.

To use the OpenID LoginHandler, you have to enable it within the LoginHandler configuration.
While enabling it is recommended to place the OpenID LoginHandler above the DefaultLoginHander.

After saving the enabled OpenID LoginHandler you now have an additional field within the Login form where you can
provide your OpenID url.