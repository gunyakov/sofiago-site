<?php

declare(strict_types=1);

use Sofiago\Controllers\AdminController;
use Sofiago\Controllers\AdminSchoolAdmissionController;
use Sofiago\Controllers\AdminVenueMapController;
use Sofiago\Controllers\Api\AuthApiController;
use Sofiago\Controllers\Api\ListingApiController;
use Sofiago\Controllers\Api\VenueApiController;
use Sofiago\Controllers\AuthController;
use Sofiago\Controllers\CommentController;
use Sofiago\Controllers\DashboardController;
use Sofiago\Controllers\FavoriteController;
use Sofiago\Controllers\ListingController;
use Sofiago\Controllers\ListingManageController;
use Sofiago\Controllers\ListingReportController;
use Sofiago\Controllers\OwnershipClaimController;
use Sofiago\Controllers\PageController;
use Sofiago\Controllers\StopShareController;
use Sofiago\Controllers\VenuePageController;
use Sofiago\Core\Router;

$router = new Router();

$router->get('/', [PageController::class, 'home']);
$router->get('/about', [PageController::class, 'about']);
$router->get('/terms', [PageController::class, 'terms']);
$router->get('/privacy', [PageController::class, 'privacy']);
$router->get('/delete-account', [PageController::class, 'deleteAccount']);
$router->get('/robots.txt', [PageController::class, 'robots']);
$router->get('/sitemap.xml', [PageController::class, 'sitemap']);

// A stop shared from the app ("Сподели") — see StopShareController's doc comment.
$router->get('/s/{code}', [StopShareController::class, 'show']);

$router->get('/sign-up', [AuthController::class, 'showRegister']);
$router->post('/sign-up', [AuthController::class, 'register']);
$router->get('/sign-in', [AuthController::class, 'showLogin']);
$router->post('/sign-in', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/verify-email/resend', [AuthController::class, 'resendVerification']);

$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/explore', [ListingController::class, 'index']);
$router->get('/listings/{slug}', [ListingController::class, 'show']);
$router->get('/api/listings', [ListingController::class, 'markers']);
$router->get('/api/listings/{slug}', [ListingController::class, 'showJson']);
$router->post('/listings/{slug}/comments', [CommentController::class, 'store']);
$router->get('/api/listings/{slug}/comments', [CommentController::class, 'indexJson']);
$router->post('/api/listings/{slug}/comments', [CommentController::class, 'storeJson']);
$router->post('/listings/{slug}/report', [ListingReportController::class, 'store']);
$router->post('/api/listings/{slug}/report', [ListingReportController::class, 'storeJson']);
$router->post('/listings/{slug}/claim-ownership', [OwnershipClaimController::class, 'store']);
$router->post('/api/listings/{slug}/claim-ownership', [OwnershipClaimController::class, 'storeJson']);

$router->get('/dashboard/listings', [ListingManageController::class, 'myListings']);
$router->get('/dashboard/listings/new', [ListingManageController::class, 'create']);
$router->post('/dashboard/listings', [ListingManageController::class, 'store']);
$router->get('/dashboard/listings/{id}/edit', [ListingManageController::class, 'edit']);
$router->post('/dashboard/listings/{id}', [ListingManageController::class, 'update']);
$router->post('/dashboard/listings/{id}/delete', [ListingManageController::class, 'destroy']);
$router->post('/dashboard/listings/{id}/renew', [ListingManageController::class, 'renew']);
$router->post('/dashboard/listings/{id}/media/{mediaId}/delete', [ListingManageController::class, 'deleteMedia']);
$router->post('/dashboard/listings/{id}/icon/delete', [ListingManageController::class, 'deleteIcon']);

$router->get('/dashboard/favorites', [FavoriteController::class, 'index']);
$router->post('/api/favorites/{id}/toggle', [FavoriteController::class, 'toggle']);

// -------------------------------------------------------------------------------------
// sofiago-flutter's native Account tab — bearer-token JSON, no session/CSRF (see Guards::
// requireApiAuth()'s doc comment). Update stays POST (not PUT/PATCH) deliberately, matching
// the web dashboard route right above: PHP only populates $_FILES from a POST multipart body,
// so a photo/icon-carrying edit request has to be POST either way.
// -------------------------------------------------------------------------------------
$router->post('/api/auth/register', [AuthApiController::class, 'register']);
$router->post('/api/auth/login', [AuthApiController::class, 'login']);
$router->post('/api/auth/logout', [AuthApiController::class, 'logout']);
$router->get('/api/auth/me', [AuthApiController::class, 'me']);

$router->get('/api/categories', [ListingApiController::class, 'categories']);
$router->get('/api/amenities', [ListingApiController::class, 'amenities']);
$router->get('/api/tags', [ListingApiController::class, 'tags']);

// Mall-map "variant A" viewer + the add-listing form's venue/floor/unit picker (see mall-map
// research thread) — public, no auth, same as /api/categories above.
$router->get('/api/venues', [VenueApiController::class, 'index']);
$router->get('/api/venues/{id}/floors', [VenueApiController::class, 'floors']);
$router->get('/api/venues/{id}/floors/{floorId}/units', [VenueApiController::class, 'units']);

// Public floor-plan page a shop's own listing links to via "View on map" — see
// VenuePageController's doc comment.
$router->get('/venues/{id}', [VenuePageController::class, 'show']);

$router->get('/api/my-listings', [ListingApiController::class, 'index']);
$router->get('/api/listings/{id}/edit', [ListingApiController::class, 'edit']);
$router->post('/api/listings', [ListingApiController::class, 'store']);
$router->post('/api/listings/{id}', [ListingApiController::class, 'update']);
$router->delete('/api/listings/{id}', [ListingApiController::class, 'destroy']);
$router->post('/api/listings/{id}/renew', [ListingApiController::class, 'renew']);
$router->delete('/api/listings/{id}/media/{mediaId}', [ListingApiController::class, 'deleteMedia']);
$router->delete('/api/listings/{id}/icon', [ListingApiController::class, 'deleteIcon']);

$router->get('/admin/listings', [AdminController::class, 'listings']);
$router->get('/admin/listings/{id}/preview', [AdminController::class, 'preview']);
$router->post('/admin/listings/{id}/approve', [AdminController::class, 'approve']);
$router->post('/admin/listings/{id}/renew', [AdminController::class, 'renewListing']);
$router->post('/admin/listings/{id}/reject', [AdminController::class, 'reject']);

$router->get('/admin/comments', [AdminController::class, 'comments']);
$router->post('/admin/comments/{id}/approve', [AdminController::class, 'approveComment']);
$router->post('/admin/comments/{id}/reject', [AdminController::class, 'rejectComment']);

$router->get('/admin/reports', [AdminController::class, 'reports']);
$router->post('/admin/reports/{id}/resolve', [AdminController::class, 'resolveReport']);
$router->post('/admin/reports/{id}/dismiss', [AdminController::class, 'dismissReport']);

$router->get('/admin/ownership-claims', [AdminController::class, 'ownershipClaims']);
$router->post('/admin/ownership-claims/{id}/approve', [AdminController::class, 'approveOwnershipClaim']);
$router->post('/admin/ownership-claims/{id}/reject', [AdminController::class, 'rejectOwnershipClaim']);

// "Карта на обект" — see AdminVenueMapController's doc comment.
$router->get('/admin/venue-maps', [AdminVenueMapController::class, 'index']);
$router->get('/admin/venue-maps/{id}', [AdminVenueMapController::class, 'edit']);
$router->post('/admin/venue-maps/{id}/publish', [AdminVenueMapController::class, 'setPublished']);
$router->post('/admin/venue-maps/{id}/floors', [AdminVenueMapController::class, 'createFloor']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}', [AdminVenueMapController::class, 'updateFloor']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/delete', [AdminVenueMapController::class, 'deleteFloor']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/image', [AdminVenueMapController::class, 'uploadFloorImage']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/image/delete', [AdminVenueMapController::class, 'deleteFloorImage']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/units', [AdminVenueMapController::class, 'saveUnit']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/units/import', [AdminVenueMapController::class, 'importUnits']);
$router->post('/admin/venue-maps/{id}/floors/{floorId}/units/{unitId}/delete', [AdminVenueMapController::class, 'deleteUnit']);

// School admission-score ("класиране") import — see AdminSchoolAdmissionController's doc comment.
$router->get('/admin/school-scores', [AdminSchoolAdmissionController::class, 'index']);
$router->post('/admin/school-scores/import', [AdminSchoolAdmissionController::class, 'import']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
