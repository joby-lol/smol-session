<?php

/**
 * smolSession
 * https://github.com/joby-lol/smol-session
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Session;

/**
 * @internal helper class for interacting with stdlib session methods and the $_SESSION
 * 
 * @codeCoverageIgnore this thing is nigh-impossible to test, and exists to make other things easier to test
 */
class SystemSessionHelper
{

    /**
     * Reference to an array of data, by default points to $_SESSION.
     * @var array<mixed>
     */
    public array $data;

    /**
     * @param array<mixed>|null &$data
     */
    public function __construct(
        array|null &$data = null,
    )
    {
        if (is_null($data))
            $this->$data = &$_SESSION;
        else
            $this->data = $data;
    }

    /**
     * Write session data and end session
     * End the current session and store session data.
     * @return bool since 7.2.0 returns true on success or false on failure.
     */
    public function session_write_close(): bool
    {
        return session_write_close();
    }

    /**
     * Initialize session data
     *
     * @param array<mixed> $options [optional] If provided, this is an associative array of options that will override the currently set session configuration directives. The keys should not include the session. prefix.
     *                       In addition to the normal set of configuration directives, a read_and_close option may also be provided. If set to TRUE, this will result in the session being closed immediately after being read, thereby avoiding unnecessary locking if the session data won't be changed.
     * @return bool This function returns true if a session was successfully started,
     *              otherwise false.
     */
    public function session_start(array $options = []): bool
    {
        return session_start($options);
    }

    /**
     * (PHP 5 >= 5.4.0)
     *
     * Returns the current session status
     * @return int **PHP_SESSION_DISABLED** if sessions are disabled.
     *             **PHP_SESSION_NONE** if sessions are enabled, but none exists.
     *             **PHP_SESSION_ACTIVE** if sessions are enabled, and one exists.
     */
    public function session_status(): int
    {
        return session_status();
    }

    /**
     * Get and/or set the current session name
     * session_name() returns the name of the current session. If `name` is given, session_name() will update the session name and return the old session name.
     *
     * @param string|null $name [optional]
     *                          The session name references the name of the session, which is
     *                          used in cookies and URLs (e.g. PHPSESSID). It
     *                          should contain only alphanumeric characters; it should be short and
     *                          descriptive (i.e. for users with enabled cookie warnings).
     *                          If <i>name</i> is specified, the name of the current
     *                          session is changed to its value.
     *                         
     *                         
     *                          <p>
     *                          The session name can't consist of digits only, at least one letter
     *                          must be present. Otherwise a new session id is generated every time.
     *                          </p>
     * @return string|bool the name of the current session.
     */
    public function session_name(string $name = null): string|bool
    {
        return session_name($name);
    }

    /**
     * Discard session array changes and finish session
     * session_abort() finishes session without saving data. Thus the original values in session data are kept.
     * @return bool since 7.2.0 returns true if a session was successfully reinitialized or false on failure.
     */
    public function session_abort(): bool
    {
        return session_abort();
    }

    /**
     * Gets the session cookie parameters.
     * @return array{lifetime:int,path:string,domain:string,secure:bool,'httponly':bool} an array with the current session cookie information, the array
     *               contains the following items:
     *               "lifetime" - The
     *               lifetime of the cookie in seconds.
     *               "path" - The path where
     *               information is stored.
     *               "domain" - The domain
     *               of the cookie.
     *               "secure" - The cookie
     *               should only be sent over secure connections.
     *               "httponly" - The
     *               cookie can only be accessed through the HTTP protocol.
     */
    public function session_get_cookie_params(): array
    {
        return session_get_cookie_params();
    }

    public function session_destroy(): bool
    {
        return session_destroy();
    }

    /**
     * Send a cookie
     *
     * @param string $name The name of the cookie.
     * @param string $value [optional]
     *                      The value of the cookie. This value is stored on the clients
     *                      computer; do not store sensitive information.
     *                      Assuming the name is 'cookiename', this
     *                      value is retrieved through $_COOKIE['cookiename']
     * @param int $expires_or_options [optional]
     *                                The time the cookie expires. This is a Unix timestamp so is
     *                                in number of seconds since the epoch. In other words, you'll
     *                                most likely set this with the time function
     *                                plus the number of seconds before you want it to expire. Or
     *                                you might use mktime.
     *                                time()+60*60*24*30 will set the cookie to
     *                                expire in 30 days. If set to 0, or omitted, the cookie will expire at
     *                                the end of the session (when the browser closes).
     *                               
     *                               
     *                                <p>
     *                                You may notice the expire parameter takes on a
     *                                Unix timestamp, as opposed to the date format Wdy, DD-Mon-YYYY
     *                                HH:MM:SS GMT, this is because PHP does this conversion
     *                                internally.
     *                                </p>
     *                                <p>
     *                                expire is compared to the client's time which can
     *                                differ from server's time.
     *                                </p>
     * @param string $path [optional]
     *                     The path on the server in which the cookie will be available on.
     *                     If set to '/', the cookie will be available
     *                     within the entire domain. If set to
     *                     '/foo/', the cookie will only be available
     *                     within the /foo/ directory and all
     *                     sub-directories such as /foo/bar/ of
     *                     domain. The default value is the
     *                     current directory that the cookie is being set in.
     * @param string $domain [optional]
     *                       The domain that the cookie is available.
     *                       To make the cookie available on all subdomains of example.com
     *                       then you'd set it to '.example.com'. The
     *                       . is not required but makes it compatible
     *                       with more browsers. Setting it to www.example.com
     *                       will make the cookie only available in the www
     *                       subdomain. Refer to tail matching in the
     *                       spec for details.
     * @param bool $secure [optional]
     *                     Indicates that the cookie should only be transmitted over a
     *                     secure HTTPS connection from the client. When set to true, the
     *                     cookie will only be set if a secure connection exists.
     *                     On the server-side, it's on the programmer to send this
     *                     kind of cookie only on secure connection (e.g. with respect to
     *                     $_SERVER["HTTPS"]).
     * @param bool $httponly [optional]
     *                       When true the cookie will be made accessible only through the HTTP
     *                       protocol. This means that the cookie won't be accessible by
     *                       scripting languages, such as JavaScript. This setting can effectively
     *                       help to reduce identity theft through XSS attacks (although it is
     *                       not supported by all browsers). Added in PHP 5.2.0.
     *                       true or false
     * @return bool If output exists prior to calling this function,
     *              setcookie will fail and return false. If
     *              setcookie successfully runs, it will return true.
     *              This does not indicate whether the user accepted the cookie.
     *              Example:
     *              ```
     *             
     *                  $value = 'something from somewhere';
     *                  setcookie("TestCookie", $value);
     *                  setcookie("TestCookie", $value, time()+3600);
     *                  setcookie("TestCookie", $value, time()+3600, "/~rasmus/", "example.com", true);
     *              ```
     */
    public function setcookie(
        string $name,
        string $value = "",
        int $expires_or_options = 0,
        string $path = "",
        string $domain = "",
        bool $secure = false,
        bool $httponly = false,
    ): bool
    {
        return setcookie(
            $name,
            $value,
            $expires_or_options,
            $path,
            $domain,
            $secure,
            $httponly,
        );
    }

}
