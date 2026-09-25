<?php

/**
 * HTTP status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

/**
 * The registered HTTP status codes (RFC 9110 and friends), with their
 * reason phrases. Responses accept any code from 100 to 599; this enum
 * supplies names and default phrases for the registered ones.
 */
enum Status: int
{
	case Continue                      = 100;
	case SwitchingProtocols            = 101;
	case Processing                    = 102;
	case EarlyHints                    = 103;
	case Ok                            = 200;
	case Created                       = 201;
	case Accepted                      = 202;
	case NonAuthoritativeInformation   = 203;
	case NoContent                     = 204;
	case ResetContent                  = 205;
	case PartialContent                = 206;
	case MultiStatus                   = 207;
	case AlreadyReported               = 208;
	case ImUsed                        = 226;
	case MultipleChoices               = 300;
	case MovedPermanently              = 301;
	case Found                         = 302;
	case SeeOther                      = 303;
	case NotModified                   = 304;
	case UseProxy                      = 305;
	case TemporaryRedirect             = 307;
	case PermanentRedirect             = 308;
	case BadRequest                    = 400;
	case Unauthorized                  = 401;
	case PaymentRequired               = 402;
	case Forbidden                     = 403;
	case NotFound                      = 404;
	case MethodNotAllowed              = 405;
	case NotAcceptable                 = 406;
	case ProxyAuthenticationRequired   = 407;
	case RequestTimeout                = 408;
	case Conflict                      = 409;
	case Gone                          = 410;
	case LengthRequired                = 411;
	case PreconditionFailed            = 412;
	case ContentTooLarge               = 413;
	case UriTooLong                    = 414;
	case UnsupportedMediaType          = 415;
	case RangeNotSatisfiable           = 416;
	case ExpectationFailed             = 417;
	case MisdirectedRequest            = 421;
	case UnprocessableContent          = 422;
	case Locked                        = 423;
	case FailedDependency              = 424;
	case TooEarly                      = 425;
	case UpgradeRequired               = 426;
	case PreconditionRequired          = 428;
	case TooManyRequests               = 429;
	case RequestHeaderFieldsTooLarge   = 431;
	case UnavailableForLegalReasons    = 451;
	case InternalServerError           = 500;
	case NotImplemented                = 501;
	case BadGateway                    = 502;
	case ServiceUnavailable            = 503;
	case GatewayTimeout                = 504;
	case HttpVersionNotSupported       = 505;
	case VariantAlsoNegotiates         = 506;
	case InsufficientStorage           = 507;
	case LoopDetected                  = 508;
	case NetworkAuthenticationRequired = 511;

	/**
	 * The status's standard reason phrase.
	 */
	public function reasonPhrase(): string
	{
		return match ($this) {
			self::Continue                      => 'Continue',
			self::SwitchingProtocols            => 'Switching Protocols',
			self::Processing                    => 'Processing',
			self::EarlyHints                    => 'Early Hints',
			self::Ok                            => 'OK',
			self::Created                       => 'Created',
			self::Accepted                      => 'Accepted',
			self::NonAuthoritativeInformation   => 'Non-Authoritative Information',
			self::NoContent                     => 'No Content',
			self::ResetContent                  => 'Reset Content',
			self::PartialContent                => 'Partial Content',
			self::MultiStatus                   => 'Multi-Status',
			self::AlreadyReported               => 'Already Reported',
			self::ImUsed                        => 'IM Used',
			self::MultipleChoices               => 'Multiple Choices',
			self::MovedPermanently              => 'Moved Permanently',
			self::Found                         => 'Found',
			self::SeeOther                      => 'See Other',
			self::NotModified                   => 'Not Modified',
			self::UseProxy                      => 'Use Proxy',
			self::TemporaryRedirect             => 'Temporary Redirect',
			self::PermanentRedirect             => 'Permanent Redirect',
			self::BadRequest                    => 'Bad Request',
			self::Unauthorized                  => 'Unauthorized',
			self::PaymentRequired               => 'Payment Required',
			self::Forbidden                     => 'Forbidden',
			self::NotFound                      => 'Not Found',
			self::MethodNotAllowed              => 'Method Not Allowed',
			self::NotAcceptable                 => 'Not Acceptable',
			self::ProxyAuthenticationRequired   => 'Proxy Authentication Required',
			self::RequestTimeout                => 'Request Timeout',
			self::Conflict                      => 'Conflict',
			self::Gone                          => 'Gone',
			self::LengthRequired                => 'Length Required',
			self::PreconditionFailed            => 'Precondition Failed',
			self::ContentTooLarge               => 'Content Too Large',
			self::UriTooLong                    => 'URI Too Long',
			self::UnsupportedMediaType          => 'Unsupported Media Type',
			self::RangeNotSatisfiable           => 'Range Not Satisfiable',
			self::ExpectationFailed             => 'Expectation Failed',
			self::MisdirectedRequest            => 'Misdirected Request',
			self::UnprocessableContent          => 'Unprocessable Content',
			self::Locked                        => 'Locked',
			self::FailedDependency              => 'Failed Dependency',
			self::TooEarly                      => 'Too Early',
			self::UpgradeRequired               => 'Upgrade Required',
			self::PreconditionRequired          => 'Precondition Required',
			self::TooManyRequests               => 'Too Many Requests',
			self::RequestHeaderFieldsTooLarge   => 'Request Header Fields Too Large',
			self::UnavailableForLegalReasons    => 'Unavailable For Legal Reasons',
			self::InternalServerError           => 'Internal Server Error',
			self::NotImplemented                => 'Not Implemented',
			self::BadGateway                    => 'Bad Gateway',
			self::ServiceUnavailable            => 'Service Unavailable',
			self::GatewayTimeout                => 'Gateway Timeout',
			self::HttpVersionNotSupported       => 'HTTP Version Not Supported',
			self::VariantAlsoNegotiates         => 'Variant Also Negotiates',
			self::InsufficientStorage           => 'Insufficient Storage',
			self::LoopDetected                  => 'Loop Detected',
			self::NetworkAuthenticationRequired => 'Network Authentication Required'
		};
	}

	/**
	 * Whether the status is a redirect (3xx).
	 */
	public function isRedirect(): bool
	{
		return $this->value >= 300 && $this->value < 400;
	}

	/**
	 * Whether a response with this status never has a body (1xx, 204,
	 * and 304).
	 */
	public function isEmpty(): bool
	{
		return $this->value < 200 || $this === self::NoContent || $this === self::NotModified;
	}
}
