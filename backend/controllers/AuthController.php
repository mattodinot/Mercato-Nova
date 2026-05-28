<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * Cree un compte acheteur (par defaut). Les vendeurs sont promus par l'admin
     * ou crees via une route specifique (a venir).
     */
    public function register(): void
    {
        Csrf::check();
        $in = $this->jsonInput();

        $email = trim((string) ($in['email'] ?? ''));
        $pass  = (string) ($in['password'] ?? '');
        $name  = trim((string) ($in['display_name'] ?? ''));
        $role  = $in['role'] ?? 'acheteur';

        $errors = [];
        if (!Validator::email($email))       $errors['email']        = 'Email invalide.';
        if (!Validator::password($pass))     $errors['password']     = 'Mot de passe : min 8 caracteres, lettres et chiffres.';
        if (!Validator::nonEmpty($name, 120))$errors['display_name'] = 'Nom requis.';
        if (!Validator::inEnum($role, ['acheteur', 'vendeur']))
                                              $errors['role']         = 'Role invalide.';

        if ($errors) {
            Response::error('Donnees invalides.', 422, ['fields' => $errors]);
        }

        if (UserModel::findByEmail($email)) {
            Response::error('Un compte existe deja pour cet email.', 409);
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $id   = UserModel::create($email, $hash, $name, $role);

        $user = UserModel::findById($id);
        Auth::login($user);
        Response::ok([
            'user' => UserModel::publicView($user),
            'csrf' => Csrf::token(),
        ]);
    }

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        Csrf::check();
        $in = $this->jsonInput();
        $email = trim((string) ($in['email'] ?? ''));
        $pass  = (string) ($in['password'] ?? '');

        $user = UserModel::findByEmail($email);
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            // Meme message pour ne pas reveler si l'email existe.
            Response::error('Identifiants invalides.', 401);
        }
        if ($user['status'] !== 'active') {
            Response::error('Compte suspendu.', 403);
        }

        Auth::login($user);
        Response::ok([
            'user' => UserModel::publicView($user),
            'csrf' => Csrf::token(),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        Csrf::check();
        Auth::logout();
        Response::ok(['logged_out' => true]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(): void
    {
        $u = Auth::user();
        Response::ok([
            'user' => $u,
            'csrf' => Csrf::token(),
        ]);
    }
}
