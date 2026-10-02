import sqlite3

def clear_database(db_path):
    """
    Clears all data from the specified SQLite database.

    Args:
        db_path (str): The path to the SQLite database file.
    """
    try:
        # Connect to the SQLite database
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        # Get the list of all tables in the database
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table';")
        tables = cursor.fetchall()

        # Disable foreign key constraints temporarily
        cursor.execute("PRAGMA foreign_keys = OFF;")

        # Iterate over each table and delete its contents
        for table in tables:
            table_name = table[0]
            cursor.execute(f"DELETE FROM {table_name};")
            print(f"Cleared data from table: {table_name}")

        # Commit the changes and close the connection
        conn.commit()
        print("All data cleared successfully.")

    except sqlite3.Error as e:
        print(f"An error occurred while clearing the database: {e}")

    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    # Specify the path to your SQLite database file
    database_path = "database.db"
    
    # Clear the database
    clear_database(database_path)