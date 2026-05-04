import os
import cv2
import numpy as np
from deepface import DeepFace

class RecognitionService:
    def __init__(self, db_path: str):
        self.db_path = db_path
        self.model_name = "Facenet"
        # Pre-cargar el modelo para mayor velocidad
        print(f"Cargando modelo {self.model_name}...")
        DeepFace.build_model(self.model_name)
        print("Modelo cargado correctamente.")

    def find_face(self, img_content: bytes):
        """
        Busca un rostro en la base de datos a partir de bytes de imagen.
        """
        # Convertir bytes a imagen de OpenCV
        nparr = np.frombuffer(img_content, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            return None

        # Guardar temporalmente para DeepFace (DeepFace.find suele requerir path o numpy array)
        # Usaremos el array directamente para mayor velocidad
        try:
            results = DeepFace.find(
                img_path=img,
                db_path=self.db_path,
                model_name=self.model_name,
                enforce_detection=False,
                silent=True
            )

            if results and len(results) > 0 and not results[0].empty:
                result = results[0]
                closest_match_path = result.iloc[0]["identity"]
                
                # Normalizar ruta para extraer carpeta y archivo
                path_parts = closest_match_path.replace("\\", "/").split("/")
                closest_folder = path_parts[-2]
                closest_file = path_parts[-1]

                return {
                    "match_folder": closest_folder,
                    "match_file": closest_file,
                    "distance": float(result.iloc[0]["distance"])
                }
            
            return None

        except Exception as e:
            print(f"Error en reconocimiento: {str(e)}")
            return None
