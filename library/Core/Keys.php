<?php
/**
 * core
 * (c) 2002-2010 siteartwork.de/core.org
 * licensing@core.org
 *
 * $Id: Keys.php 958 2010-01-05 23:58:34Z T. Suckow $
 */

 /**
 * A collection of constants defining keys for registry- and session-entries.
 *
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */
interface Core_Keys {

// -------- registry
    const REGISTRY_AUTH_OBJECT = 'com.core.registry.authObject';

// -------- ext request object
    const EXT_REQUEST_OBJECT = 'com.core.registry.extRequestObject';

// -------- app config in registry
    const REGISTRY_CONFIG_OBJECT = 'com.core.registry.config';

// -------- session auth namespace
    const SESSION_AUTH_NAMESPACE = 'com.core.session.authNamespace';

// -------- session reception controller
    const SESSION_CONTROLLER_RECEPTION = 'com.core.session.receptionController';

// -------- cache db metadata
    const CACHE_DB_METADATA = 'com.core.cache.db.metadata';

}