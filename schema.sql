CREATE DATABASE IF NOT EXISTS kanban CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kanban;

CREATE TABLE IF NOT EXISTS users (
    id           INT          NOT NULL AUTO_INCREMENT,
    github_id    VARCHAR(50)  NOT NULL UNIQUE,
    login        VARCHAR(100) NOT NULL,
    access_token TEXT         NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persists per-issue display order within each Kanban column.
-- Position is scoped to a repo; lower = higher on the board.
CREATE TABLE IF NOT EXISTS issue_positions (
    repo         VARCHAR(200)     NOT NULL,
    issue_number INT UNSIGNED     NOT NULL,
    position     FLOAT            NOT NULL DEFAULT 0,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (repo, issue_number),
    INDEX idx_repo_position (repo, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
    id            INT         NOT NULL AUTO_INCREMENT,
    user_id       INT         NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    expires_at    DATETIME    NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
