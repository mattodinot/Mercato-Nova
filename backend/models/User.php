<?php
declare(strict_types=1);

/**
 * Acces BDD pour la table user. Toutes les requetes sont preparees.
 */
final class UserModel
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM user WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, role, display_name, bio, avatar_url, status, created_at
               FROM user WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $email, string $passwordHash, string $displayName, string $role = 'acheteur'): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO user (email, password_hash, display_name, role)
             VALUES (:email, :hash, :name, :role)'
        );
        $stmt->execute([
            ':email' => $email,
            ':hash'  => $passwordHash,
            ':name'  => $displayName,
            ':role'  => $role,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Renvoie une vue publique d'un utilisateur (sans hash de mot de passe).
     */
    public static function publicView(array $u): array
    {
        return [
            'id'           => (int) $u['id'],
            'email'        => $u['email'],
            'role'         => $u['role'],
            'display_name' => $u['display_name'],
            'bio'          => $u['bio'] ?? null,
            'avatar_url'   => $u['avatar_url'] ?? null,
        ];
    }
}
