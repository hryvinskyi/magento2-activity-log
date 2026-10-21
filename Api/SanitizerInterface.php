<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Masks secrets before anything is stored
 *
 * A value under a secret field name is replaced by MASK. A credential with an authorization scheme keeps the
 * scheme ("Bearer ••••••") so people can still tell what kind of credential was sent. JSON text is decoded,
 * masked and encoded again; other values pass through unchanged. A message body of any format goes through
 * sanitizeBody() instead.
 *
 * Field names are compared in lower case, with hyphens and spaces read as underscores, on both sides: the header
 * `X-Api-Key` matches the secret name `api_key`, and `Set-Cookie` matches `cookie`.
 *
 * @api
 */
interface SanitizerInterface
{
    /**
     * What a masked value is replaced with
     */
    public const MASK = '••••••';

    /**
     * Mask the secrets in a value: an array (keys are field names), JSON text, or a scalar
     *
     * @param mixed $data
     * @return mixed
     */
    public function sanitize(mixed $data): mixed;

    /**
     * Mask the secrets in a message body of any format (a request or response body, a query string)
     *
     * The body's format is recognised from its text, and the first rule that recognises it applies:
     * - JSON object or array (text starting with `{` or `[` that decodes): every value under a secret field name
     *   is masked as sanitize() masks it. When nothing was masked, the original text is returned byte for byte;
     *   otherwise the result is encoded again with unescaped slashes and unicode and preserved zero fractions,
     *   and an empty object stays `{}` (it does not become `[]`).
     * - XML (text starting with `<` after leading whitespace that parses as XML): the content of every element and
     *   the value of every attribute whose name is a secret field is replaced by MASK. No external entity, DTD or
     *   network resource is loaded and no entity is substituted. When nothing was masked, the original text is
     *   returned byte for byte.
     * - Form-encoded (`key=value` pairs joined by `&`; a key may be `a[b][c]`): the value of each pair whose last
     *   key segment is a secret field is replaced by MASK in place; every other byte stays as it was. An empty
     *   value stays empty.
     * - Anything else (plain text, malformed JSON or XML): MASK for the whole body when the body, normalised as
     *   field names are, contains a secret field name; otherwise the body unchanged.
     *
     * Unlike sanitize(), this never returns text that still holds a secret it recognised: when a masked body
     * cannot be written back, the whole body becomes MASK.
     *
     * @param string $body
     * @return string
     */
    public function sanitizeBody(string $body): string;

    /**
     * Mask one value known to belong to a field
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeField(string $field, mixed $value): mixed;

    /**
     * Whether a field name names a secret
     *
     * True when the normalised name contains a configured secret name, or a name that is secret whatever the
     * configuration says: at least password, token, secret, authorization, cookie, api_key, apikey and
     * private_key.
     *
     * @param string $field
     * @return bool
     */
    public function isSecretField(string $field): bool;

    /**
     * Whether a stored value is a mask
     *
     * @param mixed $value
     * @return bool
     */
    public function isMasked(mixed $value): bool;
}
