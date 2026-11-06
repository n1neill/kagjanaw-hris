using System.Globalization;
using Microsoft.Data.Sqlite;

namespace KagjanawKiosk.Sync;

/// <summary>
/// Punches waiting to reach the HRIS, in a local SQLite file so nothing is
/// lost when the network or the PC goes down. A punch leaves the queue only
/// after the HRIS confirms it. Punches the HRIS refuses as invalid are moved
/// to a rejected table for the administrator instead of blocking the queue.
/// </summary>
public sealed class PunchQueue
{
    private const string TimeFormat = "yyyy-MM-dd'T'HH:mm:sszzz";

    private readonly string _connectionString;

    public PunchQueue(string databaseFile)
    {
        _connectionString = new SqliteConnectionStringBuilder { DataSource = databaseFile, Pooling = false }.ToString();

        using var connection = Open();
        Execute(connection, """
            CREATE TABLE IF NOT EXISTS punches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                biometric_user_id TEXT NOT NULL,
                logged_at TEXT NOT NULL,
                verify_type TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS rejected_punches (
                id INTEGER PRIMARY KEY,
                biometric_user_id TEXT NOT NULL,
                logged_at TEXT NOT NULL,
                verify_type TEXT NOT NULL,
                reason TEXT NOT NULL,
                rejected_at TEXT NOT NULL
            );
            """);
    }

    public QueuedPunch Enqueue(string biometricUserId, DateTimeOffset loggedAt, string verifyType = "fingerprint")
    {
        var atSecond = new DateTimeOffset(loggedAt.Ticks - (loggedAt.Ticks % TimeSpan.TicksPerSecond), loggedAt.Offset);

        using var connection = Open();
        using var command = connection.CreateCommand();
        command.CommandText = """
            INSERT INTO punches (biometric_user_id, logged_at, verify_type) VALUES ($user, $at, $verify);
            SELECT last_insert_rowid();
            """;
        command.Parameters.AddWithValue("$user", biometricUserId);
        command.Parameters.AddWithValue("$at", atSecond.ToString(TimeFormat, CultureInfo.InvariantCulture));
        command.Parameters.AddWithValue("$verify", verifyType);

        return new QueuedPunch((long)command.ExecuteScalar()!, biometricUserId, atSecond, verifyType);
    }

    /// <summary>The oldest waiting punches.</summary>
    public IReadOnlyList<QueuedPunch> Peek(int limit)
    {
        using var connection = Open();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT id, biometric_user_id, logged_at, verify_type FROM punches ORDER BY id LIMIT $limit";
        command.Parameters.AddWithValue("$limit", limit);

        var punches = new List<QueuedPunch>();
        using var reader = command.ExecuteReader();

        while (reader.Read())
        {
            punches.Add(new QueuedPunch(
                reader.GetInt64(0),
                reader.GetString(1),
                DateTimeOffset.ParseExact(reader.GetString(2), TimeFormat, CultureInfo.InvariantCulture),
                reader.GetString(3)));
        }

        return punches;
    }

    public int Count()
    {
        using var connection = Open();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT COUNT(*) FROM punches";

        return Convert.ToInt32(command.ExecuteScalar(), CultureInfo.InvariantCulture);
    }

    /// <summary>Removes punches the HRIS confirmed.</summary>
    public void Remove(IEnumerable<QueuedPunch> punches)
    {
        using var connection = Open();
        using var transaction = connection.BeginTransaction();

        foreach (var punch in punches)
        {
            using var command = connection.CreateCommand();
            command.CommandText = "DELETE FROM punches WHERE id = $id";
            command.Parameters.AddWithValue("$id", punch.Id);
            command.ExecuteNonQuery();
        }

        transaction.Commit();
    }

    /// <summary>Moves punches the HRIS refused as invalid out of the queue, keeping them with the reason.</summary>
    public void Reject(IEnumerable<QueuedPunch> punches, string reason, DateTimeOffset rejectedAt)
    {
        using var connection = Open();
        using var transaction = connection.BeginTransaction();

        foreach (var punch in punches)
        {
            using var command = connection.CreateCommand();
            command.CommandText = """
                INSERT OR REPLACE INTO rejected_punches (id, biometric_user_id, logged_at, verify_type, reason, rejected_at)
                SELECT id, biometric_user_id, logged_at, verify_type, $reason, $rejectedAt FROM punches WHERE id = $id;
                DELETE FROM punches WHERE id = $id;
                """;
            command.Parameters.AddWithValue("$id", punch.Id);
            command.Parameters.AddWithValue("$reason", reason);
            command.Parameters.AddWithValue("$rejectedAt", rejectedAt.ToString(TimeFormat, CultureInfo.InvariantCulture));
            command.ExecuteNonQuery();
        }

        transaction.Commit();
    }

    public int RejectedCount()
    {
        using var connection = Open();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT COUNT(*) FROM rejected_punches";

        return Convert.ToInt32(command.ExecuteScalar(), CultureInfo.InvariantCulture);
    }

    private SqliteConnection Open()
    {
        var connection = new SqliteConnection(_connectionString);
        connection.Open();

        return connection;
    }

    private static void Execute(SqliteConnection connection, string sql)
    {
        using var command = connection.CreateCommand();
        command.CommandText = sql;
        command.ExecuteNonQuery();
    }
}
