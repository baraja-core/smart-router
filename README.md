Smart router
============

![Integrity check](https://github.com/baraja-core/smart-router/workflows/Integrity%20check/badge.svg)

A router is a component for bidirectional translation between a URL and an application request. By bidirectional we mean the possibility to derive a presenter action from a URL, but also vice versa, to generate a corresponding URL to the action.

The package provides a default implementation of `SmartRouter`, which improves the default routing method in Nette and adds the ability to store specific URLs in a database (or other repository), including their parameters, language, and advanced configuration.

Installation
------------

The package is part of the default [Baraja Sandbox](https://github.com/baraja-core/sandbox).

Or you can install manually via Composer:

```
$ composer require baraja-core/smart-router
```

All routing rules are defined in the `RouterFactory` class, where we can inject the `SmartRouter` service like this:

```php
public static function createRouter(SmartRouter $smartRouter): RouteList
{
    $router = new RouteList;

    // Register SmartRouter
    $router[] = $smartRouter;

    // Optionally add additional routes or a collection of routes
    $router[] = self::createFrontRouter();

    return $router;
}
```

We will ensure that all requests are first processed via SmartRouter and only in case of failure will other regular routes be used.

SmartRouter
------------

`SmartRouter` is my internal implementation of how routing should work. The entire request routing process works fully automatically, and can be influenced by a very detailed configuration, which is then cached.

We divide routing into 3 separate parts:

- `Match`: Rewrite URL to application request (request processing)
- `Construct URL`: Create a URL based on an application request
- `Rewriter`: Implementation of an interface for rewriting parameters on the URL part, where most of the magic happens

The default implementation of the Rewriter is `DoctrineRewriter` ([more information](https://github.com/baraja-core/doctrine-router)), which routes URLs based on a database table. Its internal implementation is extremely effective.

For smaller applications or in the case of testing, you can use `StaticRewriter`, which performs rewriting based on a physically stored in array.

Match - Accept the request URL
------------------------------

The following text describes how the list of found parameters is compiled according to the loaded URL.

Routing is divided into 4 logical steps.

1. Find the required URL in the cache. If the record exists (it can also be negative), we route it directly and we don't have to search further
2. Preparation of the cache for configuration, if any, we will use from the cache,
3. Finding the current URL (routing) according to the internal logic (will be described below),
4. Save the routed parameters to the key-value cache, which will be used next in the first step.

The request cache contains 2 parameters:

- Expiration by constant `CACHE_EXPIRATION` (default 30 minutes)
- Tag with value `route/<presenter>:<action>`

> **Caution:** Special behavior
>
> All requests that we can handle are still being processed internally in the router, which always adds the `locale` key. If not specified during routing, we return the default language for the current site. If the default language does not exist, we return English.
>
> If the current language of the matched request does not exist, we throw an `E_NOTICE` level error.

**Sending and processing the matched application request**

Whether we find the application request in the cache or it is created by a direct match, it will always be sent by the `returnRequest()` method.

The task of this method is to set the current environment (private property `environment`) to the internal state of the router, which will be used for routing other requests and compiling URLs. The currently matched environment will only be used if it is better than the internally set one.

The environment priority rating table is stored in the `environmentScoreTable` configuration key and in the basic implementation is:

- `localhost`: 1
- `beta`: 2
- `production`: 3

At the same time, the task of this method is to set the language to the Translator.

> **Attention:** If no request has been routed, it is not possible to reliably determine the language and environment during further processing of the application and generation of links.
>
> If we generate a link to an e-mail in CLI mode (cron or another background task), for example, it is always necessary to insert the language (`locale`) and environment (`environment`) into all requests. If we do not enter these values, the router may behave differently than we expect.

**Entity `MatchRequest` - Internal logic of request processing**

The `MatchRequest` entity serves as a helper to process the current URL and return results. The instance is created by the Smart router itself.

Final processing is done only after calling a single public method `match()`, which returns a parameter field in case of success, or `null` in case of error or invalid request.

The internal logic of processing can change (and improve) over time, this text describes only general principles. A description of the specific implementation and an explanation is available directly in the implementation as a comment.

Processing procedure:

1. Finding a route (`processRoute()` method)

Currently we only support `Front` and `Admin` modules, we are looking for matching `presenter` and `action`.

We gradually try the rules:

- Is the Homepage or an empty URL? (`$slug === ''`)
- Is there an admin request? If so, we route regularly according to the mask `admin/[<locale=en cs|en>/]<presenter>/<action>`
- Transcription based on `Rewriter` + addition of parameters, environment and language.
- A regular expression (corresponds to the `<presenter> / <action>` mask) for backward compatibility of old or `lazy` URLs.

2. We will process the language of the request

The resulting language of the request is very difficult to determine, because when designing the routing kernel, we came across dozens of special cases where it is not ** easy ** to decide. It is usually a combination of domain language vs. another language slugu, or locale parameter.

The solution consists in accumulating all available languages ​​that we have available about the current URL, sorting them into fields according to keys and then selecting the best one according to priorities.

We distinguish 3 basic priority levels:

- *(best)* `URL parameter`: Contained in `?locale=en`,
- `Path / Slug`: Part of the URL is assigned to a specific language according to the rewrite,
- `Domain`: The language is typical for the currently routed domain.

3. Compilation of final parameters

In this step, the last check and purge of the parameters will be performed, which will be sent as an application request.

The minimum configuration always contains values:

- `presenter` (in the form `<module>:<presenter>`)
- `action`
- `locale` (string)
- `environment` (string, values: `localhost`, `beta`, `production`)

Construct URL - Build a URL
---------------------------

The following text describes how the URL is generated according to the specified parameters.

Before generating the URL itself, it is necessary to verify the existence of basic keys, according to which we will determine the type of URL.

It's about:

- `locale`: The language in which the URL is available,
- `environment`: The environment where the URL leads (for example, to avoid generating a link to `production` or other combinations on `beta`).

1. We find the URL in the cache according to the key composed of parameters,
2. If the cache does not contain the resulting URL, we will prepare the configuration, or read it from the cache,
3. Build the required URL based on the internal logic (described below),
4. Write to the cache and return the final URL, or `null` if it is not possible to crash.

The process of compiling a URL is much more challenging to match and must take into account more rules. However, some parts work the same.

The basis of a correctly generated URL is its uniqueness, even in the future. It must not contain logical disputes (for example, the slug language does not fit the domain language), so extremely complex logic and a set of rules are used for assembly.

Before building the final URL, an instance of the `ConstructUrlRequest` entity is created, which requires `(array $params, Rewriter $rewriter, array $config = null)` and sets the internal state of the entity for further generation based on the configuration.

When compiling, we find out the following values:

- `environment`: The environment in which we will generate the URL (different for localhost or domain production, for example),
- `locale`: URL language
- `lazy`: A `bool` flag indicating a simply assembled regular URL without using `Rewriter`, which can slow down - explained below,
- `presenter` and `action` for page type context.

The assembly itself is performed only when the `construct()` method is called, which returns `string` with an absolute URL or `null` in the event of an error.

The procedure again has many steps:

1. Obtaining a domain

- Is the environment empty, unknown, or has no default domain defined for it in the configuration? Then the current domain is retained.
- If the environment exists, we will find the best domain for the desired language,
- If the domain for the required language does not exist, we return the default domain for the environment and remember the `needLocaleParameter` flag, which says that we must pass the language in a parameter in the URL.

Before returning the domain, we check the configuration to see if we should add `www.` before the domain (`useWww` flag).

2. Processing `path` (`slug`)

Now it depends on whether we are building the path according to real parameters (using the `Rewriter` interface), or it is a `lazy link`, ie a simplified version.

Lazy link generates a path in the form `<presenter>/<action>`, generating only `/` for `Homepage:default`, only `/product` for `Product:default` and so on.

As for the real link, we generate the URL from `Rewriter` using the `rewriteByParameters()` method, while the router's internal logic performs further cleanup, such as comparing languages ​​and removing overwritten parameters. More info in the section on the transcriber.

3. We will compile the final URL according to the general format:

`<scheme>://<domain>/<path>?<parameters>`

Lazy URL - Simplified URL format
--------------------------------

The Smart Router supports the so-called `lazy URL`, which is a permanent URL generated using regular expressions, which does not require access to the `Rewriter` or the database during compilation and parsing.

Its advantage is extremely fast reading and creation. It is especially suitable for large listings of items (such as products in a catalog), where building a * nice URL * would take an unnecessarily long time and completely delay loading the page.

Lazy URLs are generated during generation by passing the value `['lazy' => true]`.

Rewriter
--------

In order to be able to dynamically rewrite the URL to the application request (parameters) and back (generate the URL), it was necessary to implement a `Smart router`, which provides a general algorithm for this task.

In practice, however, it is necessary for different clients to rewrite URLs in different ways - but mostly from a database. The `Rewriter` interface is used for this task, which is good to know especially in a situation where we need to change the method of compiling URLs, or rewrite from a static file (small websites or test progress).

**`rewriteByPath(string $path):?array;`**

Overwrites the current path (`slug`) in the parameter field. If not, it returns `null`.

Minimum configuration to return:

```
[
   'presenter' => 'Front:Homepage',
   'action' => 'default',
   'locale' => 'en',
] + other parameters
```

**`rewriteByParameters interface (array $parameters):?RewriterParametersMatch;`**

Overwrites the required parameters on Slug and other properties. Returns the result as a type entity.

The task of the `RewriterParametersMatch` entity is to carry strict information about which `path` (`slug`) the URL was rewritten to, in which language and what parameters were used.

Example:

When routing the `/wheels` URL, a rewrite was performed on `Front:Category:detail` with the parameter `id = 1`.

We have to pass this parameter separately in the field, because the router overwrites the parameter `id = 1` into the slug `/kola`, which clearly represents this ID. Removal is also necessary so that the parameter is not further preserved in the URL, because it is already passed as part of the slug (inside the DB) and can therefore always be re-routed.

📄 License
-----------

`baraja-core/smart-router` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/smart-router/blob/master/LICENSE) file for more details.
