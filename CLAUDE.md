# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A high-performance PHP HTTP router library with three-level indexing, PSR-15 middleware support, and route caching. Designed for PHP 7.2+ with broad multi-version compatibility.

**Core Features:**
- **Three-Level Route Indexing**: Static index (O(1)) → Prefix index (O(1) + O(n)) → Method index (O(n))
- **Dual Middleware Support**: PSR-15 and legacy variadic middleware
- **Route Caching**: Production-optimized serialization
- **RESTful Resources**: Automatic route generation
- **Named Routes**: URL generation with parameter substitution

## Architecture

### Router System

**Router.php** - Main routing class
**Route.php** - Value object with compiled route data
**RouteCompiler.php** - Pattern to regex compiler
**RequestHandler.php** - PSR-15 adapter

### Testing

- Test Suite: PHPUnit with 181+ tests
- Test Structure: tests/Unit/
- Base Class: tests/TestCase.php

## Development

```bash
composer install
composer test
```

## Key Details

- Namespace: Zerotoprod\HttpRouter\
- Minimum PHP: 7.2
- Dependencies: PSR-7, PSR-15, nyholm/psr7
