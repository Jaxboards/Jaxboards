![Jaxboards](https://github.com/Jaxboards/Jaxboards/blob/main/ACP/img/loginlogo.png?raw=true)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=bugs)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=coverage)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=ncloc)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=Jaxboards_Jaxboards&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)

Jaxboards is a _realtime_ forum software built on PHP/MySQL (originally in ~2010 on PHP4, and rebuilt in 2025 on PHP8).

All of the standard forum features (messaging, forums, topics, replies, online status) are all instant and highly responsive.

As users interact with the forum, those changes are reflected to all other users without them refreshing.

It delivers **outstanding** performance and creating themes is relatively easy.

## Getting Started

These instructions will get you a copy of the project up and running on your
local machine for development purposes.

### Prerequisites

- PHP 8.5 or higher
- MySQL >= 8.0.41 < 8.1
- TLS certificate (for security and protection of user data)

You'll want a dedicated database just for Jaxboards just to avoid any kind of
conflicts with anything else.

For the service setup you'll want your webserver configured like this:

- The apex domain and www subdomain should point to the `Service/` directory.
- Wildcard subdomain of that apex domain should point to the root directory.

Jaxboards must be installed in the root directory of a domain or subdomain.
You're not going to have a good time trying to get this working in a
subdirectory.

### Installation

Once you've met all the requirements, head to:
`https://$DOMAIN/Service/install.php`, replacing `$DOMAIN` with your domain.
This page gives you the option of installing the service or a standalone
Jaxboards - pick whatever suits your needs.

### Troubleshooting

Permissions issues are a major source of bugs during installation. If you
manually created any directories, you may have to assign ownership to your PHP
user using a command similar to the following:

```bash
PUBLIC_PATH="/var/www/html/<your_domain_name>/public_html" && \
  sudo chown www-data:www-data "$PUBLIC_PATH" && \
  chmod og+rwX -R "$PUBLIC_PATH" && \
  chmod u+rX -R "$PUBLIC_PATH"
```

If you plan to reuse any old themes, be prepared to update hardcoded images,
especially those that refer to the now-defunct jaxboards service domain.

## Authors

- [seanjohnson08](https://github.com/seanjohnson08) - Original developer
- [dgatwood](https://github.com/dgatwood) - Updated the codebase to support
  PHP's MySQLi interface and work with PHP7
- [wtl420](https://github.com/wtl420) - Maintainer of this fork.
- [VinnyVideo](https://github.com/VinnyVideo) - Updated Jaxboards to run in PHP8

See also the list of
[contributors](https://github.com/Jaxboards/Jaxboards/graphs/contributors) who
participated in this project.

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details

## Acknowledgments

- Contributors to the original Jaxboards Service;
  Jaxboards wouldn't exist without feedback from everyone who's supported it
  over the years.

[![SonarQube Cloud](https://sonarcloud.io/images/project_badges/sonarcloud-dark.svg)](https://sonarcloud.io/summary/new_code?id=Jaxboards_Jaxboards)
