from flask import Flask, render_template, request

app = Flask(__name__)

@app.route("/")
def login():
    return render_template("login.html")

@app.route("/registrar", methods=["POST"])
def registrar():

    usuario = request.form["usuario"]
    password = request.form["password"]

    with open("usuarios.txt", "a", encoding="utf-8") as f:
        f.write(f"Usuario: {usuario} | Password: {password}\n")

    return "Registro exitoso"
@app.route("/admin")
def admin():

    with open("usuarios.txt", "r", encoding="utf-8") as f:
        datos = f.readlines()

    return "<br>".join(datos)
if __name__ == "__main__":
    app.run(debug=True)