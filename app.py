from flask import Flask, render_template, request
import sqlite3

app = Flask(__name__)

# CREAR BASE DE DATOS
def init_db():
    conn = sqlite3.connect("usuarios.db")
    cursor = conn.cursor()

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario TEXT,
        password TEXT
    )
    """)

    conn.commit()
    conn.close()

init_db()

# LOGIN
@app.route("/")
def login():
    return render_template("login.html")

# REGISTRAR
@app.route("/registrar", methods=["POST"])
def registrar():

    usuario = request.form.get("usuario")
    password = request.form.get("password")

    conn = sqlite3.connect("usuarios.db")
    cursor = conn.cursor()

    cursor.execute(
        "INSERT INTO usuarios (usuario, password) VALUES (?, ?)",
        (usuario, password)
    )

    conn.commit()
    conn.close()

    return render_template(
    "login.html",
    error="Usuario y/o password incorrecto."
)

# ADMIN
@app.route("/admin")
def admin():

    conn = sqlite3.connect("usuarios.db")
    cursor = conn.cursor()

    cursor.execute("SELECT usuario, password FROM usuarios")
    datos = cursor.fetchall()

    conn.close()

    resultado = ""

    for usuario, password in datos:
        resultado += f"Usuario: {usuario} | Password: {password}<br>"

    return resultado if resultado else "No hay registros todavía"

if __name__ == "__main__":
    app.run(debug=True)
