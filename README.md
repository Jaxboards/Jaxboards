# Jaxboards

Jaxboards is a PHP/MySQL forum software built sometime between 2007 and Oct 6th, 2010.
It's pretty full featured and offers a unique experience over other forum softwares even today.
That being said, it's showing its age, and relies on some flash and older technologies to get by.
It is not recommended to run this in production these days.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development purposes.

### Prerequisites

Jaxboards should run with PHP 5.3 or above and MySQL 5.5.3 or above.
You'll want a dedicated database just for Jaxboards just to avoid any kind of conflicts with anything else.
It's only been tested on Apache and Linux as well, but it may work with something else.

This release of Jaxboards requires HTTPS.
There's a lot of hardcoded URLs, and defaulting that to HTTPS was the easiest solution.

You'll also want this installed in the root directory of a domain or subdomain.
You're not going to have a good time trying to get this working in a subdirectory.

For the service setup you'll want your webserver configured like this:

- The apex domain and www subdomain should point to the `Service` directory.
- Wildcard subdomain of that apex domain should point to the root directory.

### Installing

Once you've met all the requirements, head to: `https://example.com/Service/install.php`,
replacing `example.com` with your domain.
This page gives you the option of installing the service or a standalone jaxboards- pick whatever suits your needs.

## Deployment

It is not recommended to run this in production.

## Authors

* [seanjohnson08](https://github.com/seanjohnson08) - Original developer
* [dgatwood](https://github.com/dgatwood) - Updated the codebase to support PHP's MySQLi interface and work with PHP7
* [wtl420](https://github.com/wtl420) - Maintainer of this fork.

See also the list of [contributors](https://github.com/Jaxboards/Jaxboards/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* Contributors to the original [Jaxboards Service](http://jaxboards.com);
Jaxboards wouldn't exist without feedback from everyone who's supported it over the years.

