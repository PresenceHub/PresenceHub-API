-- Laravel tests use DB_DATABASE=presencehub_testing (see .env.testing.example).
-- Runs once when the Postgres data volume is first initialized (official image runs *.sql here).
SELECT format('CREATE DATABASE %I', 'presencehub_testing') WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'presencehub_testing')\gexec
